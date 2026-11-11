<?php

namespace App\Services\Accounting;

use App\Models\Documents\Document;
use App\Models\Documents\Payment;
use App\Models\Expense\Expense;
use App\Models\Inventory\StockAdjustment;
use App\Models\Invoice;
use App\Models\Purchase;
use Illuminate\Support\Facades\DB;

/**
 * What each business event posts to the books (Roadmap step 10). Every method is idempotent (re-posting replaces the
 * previous journal of the same source event) and does nothing when Accounting is off for the business.
 *
 *   POS sale            Dr Cash · Cr Sales, Cr Sales tax payable · Dr COGS, Cr Inventory (batch cost)
 *   POS return          Dr Sales returns, Dr Sales tax payable · Cr Cash · Dr Inventory, Cr COGS
 *   Purchase (plain)    Dr Inventory, Dr Input tax · Cr Cash (or A/P when "purchases are on credit")
 *   PO receipt (GRN)    Dr Inventory · Cr Goods received not billed
 *   Bill                Dr Goods received not billed, Dr Input tax, ± Price variance · Cr A/P
 *   Vendor credit       Dr A/P · Cr Input tax, Cr Inventory, ± Price variance
 *   B2B invoice         Dr A/R · Cr Sales, Cr Sales tax payable · Dr COGS, Cr Inventory
 *   Credit note         Dr Sales returns, Dr Sales tax payable · Cr A/R · Dr Inventory, Cr COGS
 *   Payment received    Dr Cash / Bank · Cr A/R            Payment made   Dr A/P · Cr Cash / Bank
 *   Stock adjustment    removed: Dr Inventory adjustments, Cr Inventory (added: the reverse)
 *   Expense approved    Dr expense account, Dr Input tax · Cr Cash / Bank (business paid) or Employee payable
 *   Expense reimbursed  Dr Employee payable · Cr Cash
 *   Opening balances    customer: Dr A/R, Cr Opening balance equity · supplier: Dr Opening balance equity, Cr A/P
 */
class LedgerPoster
{
    public function __construct(private readonly AccountingService $books)
    {
    }

    private function on(?int $businessId): bool
    {
        return $businessId && $this->books->enabled($businessId);
    }

    private static function moneyAccount(?string $method): string
    {
        return in_array($method, ['bank', 'card', 'cheque'], true) ? 'bank' : 'cash';
    }

    // ---------------------------------------------------------------------------------------------------- POS

    /**
     * POS sale at its original amounts. Returns change the invoice afterwards, so what was returned is added back here
     * and every return posts its own journal (posReturn): the books are the same whether this runs at checkout or later.
     */
    public function posSale(Invoice $invoice): void
    {
        $b = (int) $invoice->business_id;
        if (! $this->on($b)) {
            return;
        }
        $returns = DB::table('invoice_item_returns')->where('business_id', $b)->where('invoice_id', $invoice->id)->whereNull('deleted_at')->get(['refund_amount', 'taxes']);
        $returnedTax = $returns->sum(fn ($r) => collect(json_decode((string) $r->taxes, true) ?: [])->whereIn('kind', ['additive', 'included'])->sum('amount'));
        $grand = (float) $invoice->grand_total + (float) $returns->sum('refund_amount');
        $tax = (float) $invoice->tax_amount + (float) $invoice->included_tax_amount + (float) $returnedTax;
        $cost = (float) DB::table('invoice_item_lots as l')->join('invoice_items as i', 'i.id', '=', 'l.invoice_item_id')
            ->where('i.invoice_id', $invoice->id)->whereNull('l.deleted_at')->selectRaw('coalesce(sum(l.base_qty * l.base_unit_cost), 0) as c')->value('c');
        $this->books->post($b, 'pos_invoice', $invoice->id, 'sale', $invoice->created_at ?? now(), "POS sale {$invoice->invoice_no}", $invoice->invoice_no, [
            ['account' => 'cash', 'debit' => $grand],
            ['account' => 'sales', 'credit' => $grand - $tax],
            ['account' => 'tax_payable', 'credit' => $tax],
            ['account' => 'cogs', 'debit' => $cost],
            ['account' => 'inventory', 'credit' => $cost],
        ]);
    }

    public function posReturn(Invoice $invoice, string $returnNo): void
    {
        $b = (int) $invoice->business_id;
        if (! $this->on($b)) {
            return;
        }
        $rows = DB::table('invoice_item_returns')->where('business_id', $b)->where('invoice_id', $invoice->id)->where('return_no', $returnNo)->whereNull('deleted_at')->get();
        if ($rows->isEmpty()) {
            return;
        }
        $refund = 0.0;
        $tax = 0.0;
        $cost = 0.0;
        foreach ($rows as $r) {
            $refund += (float) $r->refund_amount;
            foreach (json_decode((string) $r->taxes, true) ?: [] as $t) {
                if (in_array($t['kind'] ?? null, ['additive', 'included'], true)) {
                    $tax += (float) $t['amount'];
                }
            }
            $item = DB::table('invoice_items')->where('id', $r->invoice_item_id)->first();
            if ($item && (float) $item->qty > 0) {
                $baseQty = (float) $r->qty_returned * (float) $item->base_quantity / (float) $item->qty;
                $avg = DB::table('invoice_item_lots')->where('invoice_item_id', $item->id)->whereNull('deleted_at')
                    ->selectRaw('coalesce(sum(base_qty * base_unit_cost) / nullif(sum(base_qty), 0), 0) as c')->value('c');
                $cost += $baseQty * (float) $avg;
            }
        }
        $id = (int) DB::table('invoice_item_returns')->where('return_no', $returnNo)->where('business_id', $b)->min('id');
        $this->books->post($b, 'pos_return', $id, 'return', $rows->first()->created_at ?? now(), "POS return {$returnNo} (invoice {$invoice->invoice_no})", $returnNo, [
            ['account' => 'sales_returns', 'debit' => $refund - $tax],
            ['account' => 'tax_payable', 'debit' => $tax],
            ['account' => 'cash', 'credit' => $refund],
            ['account' => 'inventory', 'debit' => $cost],
            ['account' => 'cogs', 'credit' => $cost],
        ]);
    }

    // ---------------------------------------------------------------------------------------------------- stock

    public function purchase(Purchase $p, string $source = 'purchase'): void
    {
        $b = (int) $p->business_id;
        if (! $this->on($b)) {
            return;
        }
        if ($p->trashed()) {
            $this->books->reverse($b, 'purchase', $p->id);

            return;
        }
        $value = (float) $p->paid_extra_total_cost_price > 0 ? (float) $p->paid_extra_total_cost_price : (float) $p->total_cost_price;
        $tax = (float) $p->total_cost_tax_amount;
        $fromPo = $source === 'po_receipt' || $p->document_id;
        $credit = $fromPo ? 'grni' : ($this->purchasesOnCredit($b) ? 'ap' : 'cash');
        $supplier = $p->supplier_id ? ['supplier', (int) $p->supplier_id] : null;
        $this->books->post($b, 'purchase', $p->id, 'stock_in', $p->created_at ?? now(), ($fromPo ? 'Goods received' : 'Purchase')." · batch {$p->batch_no}", $p->invoice_no, [
            ['account' => 'inventory', 'debit' => $value],
            ['account' => 'input_tax', 'debit' => $fromPo ? 0 : $tax],
            ['account' => $credit, 'credit' => $value + ($fromPo ? 0 : $tax), 'contact' => $credit === 'ap' ? $supplier : null],
        ]);
    }

    /** Business preference "accounting / purchases-on-credit": plain purchases owed to the supplier instead of paid in cash. */
    public function purchasesOnCredit(int $businessId): bool
    {
        return (bool) DB::table('preferences')->where('business_id', $businessId)->where('type', 'accounting')->where('slug', 'purchases-on-credit')
            ->whereNull('deleted_at')->value('status');
    }

    public function adjustment(StockAdjustment $a): void
    {
        $b = (int) $a->business_id;
        if (! $this->on($b)) {
            return;
        }
        $v = (float) $a->value;
        $removed = $a->direction === 'decrease';
        $this->books->post($b, 'stock_adjustment', $a->id, 'adjust', $a->date, "Stock adjustment {$a->number} ({$a->reason})", $a->number, [
            ['account' => 'inventory_adjustment', 'debit' => $removed ? $v : -$v],
            ['account' => 'inventory', 'credit' => $removed ? $v : -$v],
        ]);
    }

    // ---------------------------------------------------------------------------------------------------- documents

    public function document(Document $d): void
    {
        $b = (int) $d->business_id;
        if (! $this->on($b)) {
            return;
        }
        if (in_array($d->status, ['void', 'draft'], true) || $d->trashed()) {
            $this->books->reverse($b, 'document', $d->id);

            return;
        }
        $net = round((float) $d->total - (float) $d->tax_total, 2);
        $tax = (float) $d->tax_total;
        $total = (float) $d->total;
        $cost = (float) $d->cost_total;
        $customer = $d->customer_id ? ['customer', (int) $d->customer_id] : null;
        $supplier = $d->supplier_id ? ['supplier', (int) $d->supplier_id] : null;
        $memo = "{$d->typeLabel()} {$d->number}";
        $lines = match ($d->type) {
            'sales_invoice' => [
                ['account' => 'ar', 'debit' => $total, 'contact' => $customer],
                ['account' => 'sales', 'credit' => $net], ['account' => 'tax_payable', 'credit' => $tax],
                ['account' => 'cogs', 'debit' => $cost], ['account' => 'inventory', 'credit' => $cost],
            ],
            'credit_note' => [
                ['account' => 'sales_returns', 'debit' => $net], ['account' => 'tax_payable', 'debit' => $tax],
                ['account' => 'ar', 'credit' => $total, 'contact' => $customer],
                ['account' => 'inventory', 'debit' => $cost], ['account' => 'cogs', 'credit' => $cost],
            ],
            'bill' => [
                ['account' => 'grni', 'debit' => $cost], ['account' => 'input_tax', 'debit' => $tax],
                ['account' => 'price_variance', 'debit' => $net - $cost],
                ['account' => 'ap', 'credit' => $total, 'contact' => $supplier],
            ],
            'vendor_credit' => [
                ['account' => 'ap', 'debit' => $total, 'contact' => $supplier],
                ['account' => 'input_tax', 'credit' => $tax], ['account' => 'inventory', 'credit' => $cost],
                ['account' => 'price_variance', 'credit' => $net - $cost],
            ],
            default => null, // quotations, orders and goods received notes post nothing themselves (the GRN's batches do)
        };
        if ($lines) {
            $this->books->post($b, 'document', $d->id, 'issue', $d->date, $memo, $d->number, $lines);
        }
    }

    public function payment(Payment $p): void
    {
        $b = (int) $p->business_id;
        if (! $this->on($b)) {
            return;
        }
        if ($p->trashed()) {
            $this->books->reverse($b, 'payment', $p->id);

            return;
        }
        $money = self::moneyAccount($p->method);
        $in = $p->direction === 'in';
        $this->books->post($b, 'payment', $p->id, 'pay', $p->date, ($in ? 'Payment received ' : 'Bill payment ').$p->number, $p->number, $in ? [
            ['account' => $money, 'debit' => (float) $p->amount],
            ['account' => 'ar', 'credit' => (float) $p->amount, 'contact' => ['customer', (int) $p->customer_id]],
        ] : [
            ['account' => 'ap', 'debit' => (float) $p->amount, 'contact' => ['supplier', (int) $p->supplier_id]],
            ['account' => $money, 'credit' => (float) $p->amount],
        ]);
    }

    // ---------------------------------------------------------------------------------------------------- expense

    public function expense(Expense $e): void
    {
        $b = (int) $e->business_id;
        if (! $this->on($b)) {
            return;
        }
        if ($e->trashed() || ! in_array($e->status, ['approved', 'reimbursed'], true)) {
            $this->books->reverse($b, 'expense', $e->id);

            return;
        }
        $code = $e->category?->account_code;
        $accountId = $code ? DB::table('accounts')->where('business_id', $b)->where('code', $code)->whereNull('deleted_at')->value('id') : null;
        $account = $accountId ? (int) $accountId : 'misc_expense';
        $employee = $e->paid_by === 'employee';
        $this->books->post($b, 'expense', $e->id, 'approve', $e->date, "Expense {$e->number}".($e->merchant ? " · {$e->merchant}" : ''), $e->number, [
            ['account' => $account, 'debit' => (float) $e->amount - (float) $e->tax_amount, 'description' => $e->description],
            ['account' => 'input_tax', 'debit' => (float) $e->tax_amount],
            ['account' => $employee ? 'employee_payable' : self::moneyAccount($e->payment_method), 'credit' => (float) $e->amount, 'contact' => $employee ? ['user', (int) $e->user_id] : null],
        ]);
        if ($employee && $e->status === 'reimbursed') {
            $this->books->post($b, 'expense', $e->id, 'reimburse', $e->reimbursed_at ?? now(), "Reimbursed {$e->number}", $e->number, [
                ['account' => 'employee_payable', 'debit' => (float) $e->amount, 'contact' => ['user', (int) $e->user_id]],
                ['account' => 'cash', 'credit' => (float) $e->amount],
            ]);
        } else {
            $this->books->reverse($b, 'expense', $e->id, 'reimburse');
        }
    }

    // ---------------------------------------------------------------------------------------------------- opening balances

    public function opening(string $kind, $party): void
    {
        $b = (int) $party->business_id;
        if (! $this->on($b)) {
            return;
        }
        $v = (float) $party->opening_balance;
        if ($party->trashed() || abs($v) < 0.005) {
            $this->books->reverse($b, $kind, $party->id, 'opening');

            return;
        }
        $customer = $kind === 'customer';
        $this->books->post($b, $kind, $party->id, 'opening', $party->created_at ?? now(), "Opening balance · {$party->name}", null, $customer ? [
            ['account' => 'ar', 'debit' => $v, 'contact' => ['customer', (int) $party->id]], ['account' => 'opening_equity', 'credit' => $v],
        ] : [
            ['account' => 'opening_equity', 'debit' => $v], ['account' => 'ap', 'credit' => $v, 'contact' => ['supplier', (int) $party->id]],
        ]);
    }

    // ---------------------------------------------------------------------------------------------------- backfill

    /** Post the business's whole history (Accounting switched on later). Safe to run again. */
    public function backfill(int $businessId): array
    {
        if (! $this->on($businessId)) {
            return [];
        }
        $this->books->ensureChart($businessId);
        $counts = [];
        $scoped = fn (string $model) => $model::withoutGlobalScopes()->where('business_id', $businessId);

        $scoped(Invoice::class)->whereNull('deleted_at')->where('invoice_no', 'not like', 'PENDING-%')->orderBy('id')
            ->chunkById(200, function ($rows) use (&$counts) {
                foreach ($rows as $i) {
                    $this->posSale($i);
                    foreach (DB::table('invoice_item_returns')->where('invoice_id', $i->id)->whereNull('deleted_at')->whereNotNull('return_no')->distinct()->pluck('return_no') as $no) {
                        $this->posReturn($i, $no);
                        $counts['pos_returns'] = ($counts['pos_returns'] ?? 0) + 1;
                    }
                    $counts['pos_sales'] = ($counts['pos_sales'] ?? 0) + 1;
                }
            });
        $scoped(Purchase::class)->whereNull('deleted_at')->orderBy('id')
            ->chunkById(200, function ($rows) use (&$counts) { foreach ($rows as $p) { $this->purchase($p, $p->document_id ? 'po_receipt' : 'purchase'); $counts['purchases'] = ($counts['purchases'] ?? 0) + 1; } });
        foreach ($scoped(StockAdjustment::class)->whereNull('deleted_at')->get() as $a) { $this->adjustment($a); $counts['adjustments'] = ($counts['adjustments'] ?? 0) + 1; }
        foreach ($scoped(Document::class)->whereNull('deleted_at')->whereIn('type', ['sales_invoice', 'credit_note', 'bill', 'vendor_credit'])->whereNotIn('status', ['draft', 'void'])->get() as $d) {
            $this->document($d); $counts['documents'] = ($counts['documents'] ?? 0) + 1;
        }
        foreach ($scoped(Payment::class)->whereNull('deleted_at')->get() as $p) { $this->payment($p); $counts['payments'] = ($counts['payments'] ?? 0) + 1; }
        foreach ($scoped(Expense::class)->whereNull('deleted_at')->whereIn('status', ['approved', 'reimbursed'])->get() as $e) { $this->expense($e); $counts['expenses'] = ($counts['expenses'] ?? 0) + 1; }
        foreach ($scoped(\App\Models\Customer::class)->whereNull('deleted_at')->where('opening_balance', '!=', 0)->get() as $c) { $this->opening('customer', $c); }
        foreach ($scoped(\App\Models\Supplier::class)->whereNull('deleted_at')->where('opening_balance', '!=', 0)->get() as $s) { $this->opening('supplier', $s); }

        return $counts;
    }
}
