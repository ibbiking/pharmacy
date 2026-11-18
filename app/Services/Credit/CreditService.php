<?php

namespace App\Services\Credit;

use App\Models\Customer;
use App\Models\Documents\Document;
use App\Models\Invoice;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Documents\DocumentService;
use App\Services\Sales\Exceptions\SaleRuleViolation;
use Illuminate\Support\Facades\DB;

/**
 * Credit in every business type (docs/BUSINESS_TYPES_AND_SERVICES.md, phases 3–4).
 *
 * Money owed is kept in ONE place for every type: the documents + payments + allocations of the B2B engine, so
 * statements, ageing, customer credit and the books work the same everywhere. A Pharmacy / Retail business never sees
 * the document screens: its POS sales on credit, their returns and its purchases on credit create **linked documents**
 * in the background (origin = pos / pos_return / purchase). A linked document never moves stock and never posts its
 * own journal; the POS sale / return / purchase did both (LedgerPoster posts them to AR / AP instead of cash).
 */
class CreditService
{
    public function __construct(private readonly DocumentService $documents)
    {
    }

    /** What the customer owes now (opening balance + open invoices − open credit − unapplied payments). */
    public function customerBalance(int $customerId): float
    {
        return round((float) Customer::query()->select('customers.id')
            ->selectSub(\App\Http\Controllers\Admin\CustomerController::balanceSql(), 'b')->whereKey($customerId)->value('b'), 2);
    }

    /** Refuses a sale that would take the customer over their credit limit (no limit set = no check). */
    public function assertWithinLimit(Customer $customer, float $adding): void
    {
        if ($customer->credit_limit === null || (float) $customer->credit_limit <= 0 || $adding <= 0) {
            return;
        }
        $balance = $this->customerBalance($customer->id);
        if ($balance + $adding > (float) $customer->credit_limit + 0.004) {
            throw new SaleRuleViolation(SaleRuleViolation::CREDIT_LIMIT,
                sprintf('%s would go over their credit limit (owes %.2f + %.2f on this sale > limit %.2f).', $customer->name, $balance, $adding, $customer->credit_limit),
                ['balance' => $balance, 'adding' => $adding, 'credit_limit' => (float) $customer->credit_limit]);
        }
    }

    /**
     * A POS sale to a saved customer that was not paid in full: the linked invoice carries what is owed; the part paid
     * at the till is a payment received, allocated to it.
     */
    public function posSale(Invoice $invoice, Customer $customer, float $paid, ?int $userId): Document
    {
        $total = round((float) $invoice->grand_total, 2);
        $paid = round(max(0, min($paid, $total)), 2);
        $doc = Document::create([
            'type' => 'sales_invoice', 'origin' => 'pos', 'invoice_id' => $invoice->id, 'number' => $invoice->invoice_no,
            'customer_id' => $customer->id, 'date' => today()->toDateString(),
            'due_date' => today()->addDays((int) ($customer->payment_terms_days ?? 0))->toDateString(),
            'reference' => 'POS sale', 'status' => 'unpaid', 'subtotal' => $total, 'discount_total' => 0, 'tax_total' => round((float) $invoice->tax_amount, 2),
            'total' => $total, 'paid' => 0, 'balance' => $total, 'cost_total' => 0, 'created_by' => $userId, 'issued_at' => now(),
        ]);
        if ($paid > 0) {
            $this->documents->recordPayment('in', [
                'customer_id' => $customer->id, 'date' => today()->toDateString(), 'amount' => $paid, 'method' => 'cash',
                'reference' => "POS {$invoice->invoice_no}", 'allocations' => [['document_id' => $doc->id, 'amount' => $paid]],
            ], $this->user($userId));
        }
        $doc->refresh();
        $invoice->update(['payment_status' => $doc->status === 'paid' ? 'paid' : ($doc->paid > 0 ? 'partial' : 'unpaid')]);

        return $doc;
    }

    /**
     * Return of a POS sale made on credit: nothing is paid out in cash; a linked credit note lowers what the customer
     * owes on that sale, and anything above it stays as the customer's credit.
     */
    public function posReturn(Invoice $invoice, float $amount, string $returnNo, ?int $userId): ?Document
    {
        $linked = $this->linkedInvoice($invoice);
        $amount = round($amount, 2);
        if (! $linked || $amount <= 0) {
            return null;
        }
        $linked = Document::query()->whereKey($linked->id)->lockForUpdate()->first();
        $credit = Document::create([
            'type' => 'credit_note', 'origin' => 'pos_return', 'invoice_id' => $invoice->id, 'source_id' => $linked->id, 'number' => $returnNo,
            'customer_id' => $linked->customer_id, 'date' => today()->toDateString(), 'reference' => "Return of {$invoice->invoice_no}",
            'status' => 'open_credit', 'subtotal' => $amount, 'total' => $amount, 'paid' => 0, 'balance' => $amount, 'created_by' => $userId, 'issued_at' => now(),
        ]);
        $this->documents->applyCredit($credit, $linked);
        $linked->refresh();
        $invoice->update(['payment_status' => $linked->status === 'paid' ? 'paid' : ($linked->paid > 0 ? 'partial' : 'unpaid')]);

        return $credit->fresh();
    }

    /** The linked invoice of a POS sale on credit, if any. */
    public function linkedInvoice(Invoice $invoice): ?Document
    {
        return Document::query()->where('type', 'sales_invoice')->where('origin', 'pos')->where('invoice_id', $invoice->id)->first();
    }

    /**
     * A purchase (one supplier bill, one or more batches) that was not paid in full: the linked bill carries what is
     * owed to the supplier; the part paid now is a bill payment allocated to it.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Purchase>  $batches
     */
    public function purchaseBill($batches, Supplier $supplier, float $total, float $paid, ?string $billNo, ?int $userId): Document
    {
        $total = round($total, 2);
        $paid = round(max(0, min($paid, $total)), 2);
        $first = $batches->first();
        $doc = Document::create([
            'type' => 'bill', 'origin' => 'purchase', 'purchase_id' => $first->id, 'number' => $billNo ?: ('PUR-'.str_pad((string) $first->id, 6, '0', STR_PAD_LEFT)),
            'supplier_id' => $supplier->id, 'date' => today()->toDateString(),
            'due_date' => today()->addDays((int) ($supplier->payment_terms_days ?? 0))->toDateString(),
            'reference' => 'Purchase', 'status' => 'unpaid', 'subtotal' => $total, 'total' => $total, 'paid' => 0, 'balance' => $total,
            'cost_total' => $total, 'created_by' => $userId, 'issued_at' => now(),
        ]);
        if ($paid > 0) {
            $this->documents->recordPayment('out', [
                'supplier_id' => $supplier->id, 'date' => today()->toDateString(), 'amount' => $paid, 'method' => 'cash',
                'reference' => "Purchase {$doc->number}", 'allocations' => [['document_id' => $doc->id, 'amount' => $paid]],
            ], $this->user($userId));
        }
        $doc->refresh();
        $status = $doc->status === 'paid' ? 'paid' : ($doc->paid > 0 ? 'partial' : 'unpaid');
        \App\Models\Purchase::query()->whereIn('id', $batches->pluck('id'))->update(['payment_status' => $status, 'amount_paid' => $paid]);

        return $doc;
    }

    /** The linked bill of a purchase batch (any batch of the same supplier bill), if any. */
    public function linkedBill(\App\Models\Purchase $purchase): ?Document
    {
        return Document::query()->where('type', 'bill')->where('origin', 'purchase')
            ->where(fn ($q) => $q->where('purchase_id', $purchase->id)->orWhereIn('purchase_id', \App\Models\Purchase::query()
                ->where('invoice_no', $purchase->invoice_no)->where('supplier_id', $purchase->supplier_id)->whereNotNull('invoice_no')->select('id')))
            ->first();
    }

    private function user(?int $userId): User
    {
        return User::query()->find($userId) ?? auth()->user() ?? User::query()->firstOrFail();
    }
}
