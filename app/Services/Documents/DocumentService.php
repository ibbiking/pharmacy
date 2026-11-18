<?php

namespace App\Services\Documents;

use App\Events\DocumentChanged;
use App\Events\PaymentChanged;
use App\Models\BaseStockSalePrice;
use App\Models\Customer;
use App\Models\Documents\Document;
use App\Models\Documents\DocumentLine;
use App\Models\Documents\DocumentLineLot;
use App\Models\Documents\Payment;
use App\Models\Documents\PaymentAllocation;
use App\Models\Inventory\Warehouse;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Tax;
use App\Models\User;
use App\Services\Numbering\NumberSeriesService;
use App\Services\Sales\LotAllocator;
use App\Services\Sales\PackagingService;
use App\Services\Sales\SalePreferenceResolver;
use App\Services\Sales\SalePriceCalculator;
use App\Services\Stock\PurchaseService;
use App\Services\Stock\StockMovementService;
use App\Services\Stock\WarehouseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * B2B documents (Roadmap steps 8–9). The server is the authority for prices, discounts, taxes, totals and stock.
 *
 * Sales:      quotation (draft → sent → accepted / declined → converted)
 *             → sales order (draft → open → invoiced / cancelled)
 *             → invoice (draft → unpaid → partial → paid; void while nothing is applied): issuing takes stock FEFO
 *             → credit note from an invoice (draft → open_credit / applied): restocks the exact batches the invoice took
 * Purchasing: purchase order (draft → sent → partially_received → received / cancelled)
 *             → goods received note (creates the batches through PurchaseService, like a purchase)
 *             → bill for what was received (draft → unpaid → partial → paid)
 *             → vendor credit from a bill (returned goods leave the batches FEFO)
 * Payments:   received from customers / paid to suppliers, allocated to open invoices / bills; the rest stays as credit.
 * Every change raises DocumentChanged / PaymentChanged so Accounting (when on) posts it.
 */
class DocumentService
{
    public const EDITABLE = ['quotation', 'sales_order', 'sales_invoice', 'credit_note', 'purchase_order', 'bill', 'vendor_credit'];

    public function __construct(
        private readonly PackagingService $packaging,
        private readonly NumberSeriesService $numbers,
        private readonly WarehouseService $warehouses,
        private readonly LotAllocator $lots,
        private readonly StockMovementService $moves,
        private readonly PurchaseService $purchases,
    ) {
    }

    // ============================================================================================ drafts

    public function save(string $type, array $input, ?Document $doc, User $user): Document
    {
        abort_unless(in_array($type, self::EDITABLE, true), 404);
        if ($doc && $doc->status !== 'draft') {
            throw ValidationException::withMessages(['document' => 'Only drafts can be edited.']);
        }
        $sales = Document::TYPES[$type]['side'] === 'sales';
        $source = $this->sourceFor($type, $input['source_id'] ?? $doc?->source_id);

        $head = validator($input, [
            'customer_id' => [$sales ? 'required' : 'nullable', 'integer'],
            'supplier_id' => [$sales ? 'nullable' : 'required', 'integer'],
            'warehouse_id' => ['nullable', 'integer'],
            'date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'terms' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
        ], ['lines.required' => 'Add at least one line.', 'customer_id.required' => 'Pick the customer.', 'supplier_id.required' => 'Pick the supplier.',
            'due_date.after_or_equal' => 'The due date can’t be before the document date.'])->validate();

        $party = $sales ? Customer::query()->find($head['customer_id']) : Supplier::query()->find($head['supplier_id']);
        if (! $party) {
            throw ValidationException::withMessages([$sales ? 'customer_id' : 'supplier_id' => $sales ? 'Pick one of your customers.' : 'Pick one of your suppliers.']);
        }
        if ($source && (int) ($sales ? $source->customer_id : $source->supplier_id) !== (int) $party->id) {
            throw ValidationException::withMessages([$sales ? 'customer_id' : 'supplier_id' => 'It must be the same '.($sales ? 'customer' : 'supplier').' as on '.$source->number.'.']);
        }
        $businessId = (int) $party->business_id;
        $warehouseId = $this->warehouses->enabled($businessId) && in_array($type, ['sales_invoice', 'purchase_order', 'vendor_credit', 'sales_order', 'quotation'], true)
            ? $this->warehouses->resolve($businessId, $head['warehouse_id'] ?? null) : null;

        $lines = $this->checkLines($type, $head['lines'], $source, $user, $businessId, $doc);

        return DB::transaction(function () use ($type, $head, $doc, $party, $sales, $source, $warehouseId, $lines, $user, $businessId) {
            $doc ??= new Document(['type' => $type, 'number' => $this->numbers->next($businessId, Document::TYPES[$type]['series']), 'status' => 'draft', 'created_by' => $user->id]);
            $terms = $sales ? (int) $party->payment_terms_days : (int) ($party->payment_terms_days ?? 0);
            $doc->fill([
                'customer_id' => $sales ? $party->id : null, 'supplier_id' => $sales ? null : $party->id, 'warehouse_id' => $warehouseId,
                'source_id' => $source?->id, 'date' => $head['date'],
                'due_date' => $head['due_date'] ?? (in_array($type, ['sales_invoice', 'bill'], true) ? \Carbon\Carbon::parse($head['date'])->addDays($terms)->toDateString() : ($type === 'quotation' ? \Carbon\Carbon::parse($head['date'])->addDays(15)->toDateString() : null)),
                'reference' => $head['reference'] ?? null, 'notes' => $head['notes'] ?? null, 'terms' => $head['terms'] ?? null,
            ]);
            $doc->save();
            $doc->lines()->get()->each->forceDelete(); // a draft's lines are rewritten as a whole
            foreach ($lines as $i => $l) {
                DocumentLine::create($l + ['document_id' => $doc->id, 'position' => $i]);
            }
            $this->totals($doc);

            return $doc->fresh('lines');
        });
    }

    /** Convert a document into the next one as a draft (quotation → order / invoice, order → invoice, PO → bill, invoice → credit note, bill → vendor credit). */
    public function convert(Document $from, string $to, User $user): Document
    {
        $allowed = [
            'quotation' => ['sales_order', 'sales_invoice'], 'sales_order' => ['sales_invoice'], 'sales_invoice' => ['credit_note'],
            'purchase_order' => ['bill'], 'bill' => ['vendor_credit'],
        ];
        abort_unless(in_array($to, $allowed[$from->type] ?? [], true), 404);
        $okStatus = match ($from->type) {
            'quotation' => in_array($from->status, ['draft', 'sent', 'accepted'], true),
            'sales_order' => $from->status === 'open',
            'sales_invoice', 'bill' => in_array($from->status, ['unpaid', 'partial', 'paid'], true),
            'purchase_order' => in_array($from->status, ['partially_received', 'received'], true),
            default => false,
        };
        if (! $okStatus) {
            throw ValidationException::withMessages(['document' => match ($from->type) {
                'sales_order' => 'Confirm the order first.',
                'purchase_order' => 'Receive the goods first: a bill covers what was received.',
                default => 'This document can’t be converted in its current state.',
            }]);
        }

        $lines = [];
        foreach ($from->lines as $l) {
            $left = $this->convertibleQty($from, $l, $to);
            if ($left <= 0.00001) {
                continue;
            }
            $lines[] = [
                'source_line_id' => $l->id, 'product_id' => $l->product_id, 'category_id' => $l->category_id, 'quantity' => $left,
                'unit_price' => $l->unit_price, 'discount_percent' => $l->discount_percent, 'tax_id' => $l->tax_id, 'description' => $l->description,
            ];
        }
        if (! $lines) {
            throw ValidationException::withMessages(['document' => 'Nothing left to convert: every line is already covered.']);
        }

        $doc = $this->save($to, [
            'customer_id' => $from->customer_id, 'supplier_id' => $from->supplier_id, 'warehouse_id' => $from->warehouse_id, 'source_id' => $from->id,
            'date' => today()->toDateString(), 'reference' => $from->reference ?: $from->number, 'notes' => $from->notes, 'terms' => $from->terms, 'lines' => $lines,
        ], null, $user);
        if ($from->type === 'quotation') {
            $from->update(['status' => 'converted']);
        }

        return $doc;
    }

    // ============================================================================================ status actions

    public function action(Document $doc, string $action, User $user): Document
    {
        return DB::transaction(function () use ($doc, $action, $user) {
            $doc = Document::query()->whereKey($doc->id)->lockForUpdate()->firstOrFail();
            $fail = fn (string $m) => throw ValidationException::withMessages(['document' => $m]);
            switch ("{$doc->type}:{$action}") {
                case 'quotation:send':
                case 'purchase_order:send':
                    $doc->status === 'draft' || $fail('Already sent.');
                    $doc->update(['status' => 'sent', 'issued_at' => now()]);
                    break;
                case 'quotation:accept':
                case 'quotation:decline':
                    in_array($doc->status, ['draft', 'sent'], true) || $fail('This quotation was already answered.');
                    $doc->update(['status' => $action === 'accept' ? 'accepted' : 'declined']);
                    break;
                case 'sales_order:confirm':
                    $doc->status === 'draft' || $fail('Already confirmed.');
                    $doc->update(['status' => 'open', 'issued_at' => now()]);
                    break;
                case 'sales_order:cancel':
                case 'purchase_order:cancel':
                    $doc->lines->sum('done_qty') <= 0 || $fail($doc->type === 'sales_order' ? 'Part of this order was invoiced: it can’t be cancelled.' : 'Goods were received on this order: it can’t be cancelled.');
                    in_array($doc->status, ['draft', 'open', 'sent'], true) || $fail('It can’t be cancelled now.');
                    $doc->update(['status' => 'cancelled']);
                    break;
                case 'sales_invoice:issue':
                    $this->issueInvoice($doc);
                    break;
                case 'credit_note:issue':
                    $this->issueCreditNote($doc);
                    break;
                case 'bill:issue':
                    $this->issueBill($doc);
                    break;
                case 'vendor_credit:issue':
                    $this->issueVendorCredit($doc);
                    break;
                case 'sales_invoice:void':
                case 'bill:void':
                case 'credit_note:void':
                case 'vendor_credit:void':
                    $this->void($doc);
                    break;
                case 'quotation:delete':
                case 'sales_order:delete':
                case 'purchase_order:delete':
                case 'sales_invoice:delete':
                case 'bill:delete':
                case 'credit_note:delete':
                case 'vendor_credit:delete':
                    $doc->status === 'draft' || $fail('Only drafts can be deleted. Void or cancel it instead.');
                    $doc->lines()->get()->each->delete();
                    $doc->delete();
                    break;
                default:
                    abort(404);
            }

            return $doc->fresh('lines') ?? $doc;
        });
    }

    private function issueInvoice(Document $doc): void
    {
        if ($doc->status !== 'draft') {
            throw ValidationException::withMessages(['document' => 'This invoice was already issued.']);
        }
        $customer = Customer::query()->findOrFail($doc->customer_id);
        if ($customer->credit_limit !== null && (float) $customer->credit_limit > 0) {
            $balance = (float) Customer::query()->select('customers.id')->selectSub(\App\Http\Controllers\Admin\CustomerController::balanceSql(), 'b')->whereKey($customer->id)->value('b');
            if ($balance + $doc->total > (float) $customer->credit_limit + 0.004) {
                throw ValidationException::withMessages(['document' => sprintf('This invoice would take %s over the credit limit (balance %.2f + %.2f > %.2f).', $customer->name, $balance, $doc->total, $customer->credit_limit)]);
            }
        }

        $cost = 0.0;
        $remaining = [];
        foreach ($doc->lines as $i => $line) {
            if (! $line->product_id) {
                continue;
            }
            $settings = app(SalePreferenceResolver::class)->forProduct((int) $line->product_id);
            $lots = $this->lots->sellableLots((int) $line->product_id, $settings->lotMode, lock: true, warehouseId: $doc->warehouse_id ?: false);
            foreach ($lots as $lot) {
                $remaining[$lot->id] ??= (float) $lot->remaining_base_stock;
            }
            try {
                $allocations = $this->lots->allocate($lots, $remaining, (float) $line->base_qty);
            } catch (\App\Services\Sales\Exceptions\InsufficientStockException $e) {
                $have = round($this->packaging->fromBase((int) $line->product_id, (int) $line->category_id, $e->availableBaseQty), 2);
                throw ValidationException::withMessages(['document' => "Not enough {$line->product?->product_name} in stock".($doc->warehouse_id ? ' in this warehouse' : '')." (available: {$have} {$line->unit?->name})."]);
            }
            $lineCost = 0.0;
            foreach ($allocations as ['lot' => $lot, 'qty' => $qty]) {
                $unitCost = $this->moves->baseCost((int) $lot->purchase_id);
                DocumentLineLot::create(['document_line_id' => $line->id, 'lot_id' => $lot->id, 'purchase_id' => $lot->purchase_id, 'base_qty' => $qty, 'base_unit_cost' => $unitCost]);
                $lineCost += $qty * $unitCost;
            }
            $line->update(['cost_amount' => round($lineCost, 2)]);
            $cost += $lineCost;
            $this->bumpCounter((int) $line->product_id, -(float) $line->base_qty);
        }
        foreach ($remaining as $lotId => $qty) {
            BaseStockSalePrice::query()->whereKey($lotId)->update(['remaining_base_stock' => round($qty, 6)]);
        }

        $doc->update(['status' => 'unpaid', 'issued_at' => now(), 'cost_total' => round($cost, 2), 'paid' => 0, 'balance' => $doc->total]);
        $this->markSourceDone($doc);
        $this->refreshStatus($doc);
        event(new DocumentChanged($doc->fresh('lines'), 'issued'));
    }

    private function issueCreditNote(Document $doc): void
    {
        if ($doc->status !== 'draft') {
            throw ValidationException::withMessages(['document' => 'This credit note was already issued.']);
        }
        $invoice = Document::query()->whereKey($doc->source_id)->lockForUpdate()->firstOrFail();
        $cost = 0.0;
        foreach ($doc->lines as $line) {
            $src = DocumentLine::query()->whereKey($line->source_line_id)->lockForUpdate()->firstOrFail();
            if ($line->qty > $src->qty - $src->done_qty + 0.00001) {
                throw ValidationException::withMessages(['document' => "Only ".round($src->qty - $src->done_qty, 2)." of {$src->product?->product_name} can still be credited."]);
            }
            $src->update(['done_qty' => round($src->done_qty + $line->qty, 4)]);
            if (! $line->product_id) {
                continue;
            }
            $toReturn = (float) $line->base_qty;
            $lineCost = 0.0;
            foreach (DocumentLineLot::query()->where('document_line_id', $src->id)->orderBy('id')->lockForUpdate()->get() as $taken) {
                if ($toReturn <= 0.00001) {
                    break;
                }
                $back = min($toReturn, (float) $taken->base_qty - (float) $taken->returned_base_qty);
                if ($back <= 0) {
                    continue;
                }
                $taken->update(['returned_base_qty' => round((float) $taken->returned_base_qty + $back, 6)]);
                BaseStockSalePrice::query()->whereKey($taken->lot_id)->increment('remaining_base_stock', $back);
                DocumentLineLot::create(['document_line_id' => $line->id, 'lot_id' => $taken->lot_id, 'purchase_id' => $taken->purchase_id, 'base_qty' => $back, 'base_unit_cost' => $taken->base_unit_cost]);
                $lineCost += $back * (float) $taken->base_unit_cost;
                $toReturn -= $back;
            }
            $line->update(['cost_amount' => round($lineCost, 2)]);
            $cost += $lineCost;
            $this->bumpCounter((int) $line->product_id, (float) $line->base_qty - $toReturn);
        }
        $doc->update(['status' => 'open_credit', 'issued_at' => now(), 'cost_total' => round($cost, 2), 'paid' => 0, 'balance' => $doc->total]);
        $this->applyCredit($doc, $invoice);
        event(new DocumentChanged($doc->fresh('lines'), 'issued'));
    }

    private function issueBill(Document $doc): void
    {
        if ($doc->status !== 'draft') {
            throw ValidationException::withMessages(['document' => 'This bill was already entered.']);
        }
        foreach ($doc->lines as $line) {
            $po = DocumentLine::query()->whereKey($line->source_line_id)->first();
            if ($po && $line->qty > $po->done_qty - $this->billedQty($po->id, $doc->id) + 0.00001) {
                throw ValidationException::withMessages(['document' => "Only what was received can be billed for {$po->product?->product_name}."]);
            }
        }
        // Received value of these quantities (what the goods received note put into stock).
        $received = 0.0;
        foreach ($doc->lines as $line) {
            $po = DocumentLine::query()->whereKey($line->source_line_id)->first();
            $received += $po ? $line->qty * ($po->qty > 0 ? $po->net_amount / $po->qty : 0) : $line->net_amount;
        }
        $doc->update(['status' => 'unpaid', 'issued_at' => now(), 'cost_total' => round($received, 2), 'paid' => 0, 'balance' => $doc->total]);
        $this->refreshPurchaseOrder($doc->source_id);
        $this->refreshStatus($doc);
        event(new DocumentChanged($doc->fresh('lines'), 'issued'));
    }

    private function issueVendorCredit(Document $doc): void
    {
        if ($doc->status !== 'draft') {
            throw ValidationException::withMessages(['document' => 'This vendor credit was already issued.']);
        }
        $bill = Document::query()->whereKey($doc->source_id)->lockForUpdate()->firstOrFail();
        $cost = 0.0;
        foreach ($doc->lines as $i => $line) {
            $src = DocumentLine::query()->whereKey($line->source_line_id)->lockForUpdate()->firstOrFail();
            if ($line->qty > $src->qty - $src->done_qty + 0.00001) {
                throw ValidationException::withMessages(['document' => 'Only '.round($src->qty - $src->done_qty, 2)." of {$src->product?->product_name} can still be returned."]);
            }
            $src->update(['done_qty' => round($src->done_qty + $line->qty, 4)]);
            if (! $line->product_id) {
                continue;
            }
            $lots = BaseStockSalePrice::query()->where('product_id', $line->product_id)->where('remaining_base_stock', '>', 0)
                ->when($doc->warehouse_id, fn ($q) => $q->where('warehouse_id', $doc->warehouse_id))
                ->orderByRaw('expiry_date IS NULL')->orderBy('expiry_date')->orderBy('id')->lockForUpdate()->get();
            $need = (float) $line->base_qty;
            if ((float) $lots->sum('remaining_base_stock') + 0.00001 < $need) {
                throw ValidationException::withMessages(['document' => "Not enough {$line->product?->product_name} in stock to return."]);
            }
            $lineCost = 0.0;
            foreach ($lots as $lot) {
                if ($need <= 0.00001) {
                    break;
                }
                $take = min($need, (float) $lot->remaining_base_stock);
                $lot->update(['remaining_base_stock' => round((float) $lot->remaining_base_stock - $take, 6)]);
                $unitCost = $this->moves->baseCost((int) $lot->purchase_id);
                DocumentLineLot::create(['document_line_id' => $line->id, 'lot_id' => $lot->id, 'purchase_id' => $lot->purchase_id, 'base_qty' => $take, 'base_unit_cost' => $unitCost]);
                $lineCost += $take * $unitCost;
                $need -= $take;
            }
            $line->update(['cost_amount' => round($lineCost, 2)]);
            $cost += $lineCost;
            $this->bumpCounter((int) $line->product_id, -(float) $line->base_qty);
        }
        $doc->update(['status' => 'open_credit', 'issued_at' => now(), 'cost_total' => round($cost, 2), 'paid' => 0, 'balance' => $doc->total]);
        $this->applyCredit($doc, $bill);
        event(new DocumentChanged($doc->fresh('lines'), 'issued'));
    }

    /** Void an issued invoice / bill / credit: only while no payment or credit was applied to it. Stock goes back. */
    private function void(Document $doc): void
    {
        if (! in_array($doc->status, ['unpaid', 'open_credit'], true) || PaymentAllocation::query()->where('document_id', $doc->id)->exists()
            || ($doc->type !== 'credit_note' && $doc->type !== 'vendor_credit' && $doc->children()->whereIn('type', ['credit_note', 'vendor_credit'])->where('status', '!=', 'void')->exists())) {
            throw ValidationException::withMessages(['document' => 'Only documents with nothing paid or credited against them can be voided.']);
        }
        if (in_array($doc->type, ['credit_note', 'vendor_credit'], true) && PaymentAllocation::query()->where('credit_document_id', $doc->id)->exists()) {
            // Undo what the credit settled, then its stock.
            foreach (PaymentAllocation::query()->where('credit_document_id', $doc->id)->get() as $a) {
                $target = Document::query()->whereKey($a->document_id)->lockForUpdate()->first();
                if ($target) {
                    $target->update(['paid' => round($target->paid - $a->amount, 2), 'balance' => round($target->balance + $a->amount, 2)]);
                    $this->refreshStatus($target);
                }
                $a->delete();
            }
        }
        foreach ($doc->lines as $line) {
            foreach (DocumentLineLot::query()->where('document_line_id', $line->id)->get() as $moved) {
                $qty = (float) $moved->base_qty;
                if ($doc->type === 'credit_note') {
                    BaseStockSalePrice::query()->whereKey($moved->lot_id)->decrement('remaining_base_stock', $qty);
                    DocumentLineLot::query()->where('lot_id', $moved->lot_id)->whereIn('document_line_id', DocumentLine::query()->where('id', $line->source_line_id)->pluck('id'))
                        ->decrement('returned_base_qty', $qty);
                } else {
                    BaseStockSalePrice::query()->whereKey($moved->lot_id)->increment('remaining_base_stock', $qty);
                }
            }
            if ($line->product_id && in_array($doc->type, ['sales_invoice', 'credit_note', 'vendor_credit'], true)) {
                $this->bumpCounter((int) $line->product_id, $doc->type === 'credit_note' ? -(float) $line->base_qty : (float) $line->base_qty);
            }
            if ($line->source_line_id && in_array($doc->type, ['credit_note', 'vendor_credit', 'sales_invoice'], true)) {
                DocumentLine::query()->whereKey($line->source_line_id)->decrement('done_qty', $line->qty);
            }
        }
        $doc->update(['status' => 'void', 'balance' => 0]);
        if ($doc->type === 'bill') {
            $this->refreshPurchaseOrder($doc->source_id);
        }
        if ($doc->type === 'sales_invoice' && $doc->source_id) {
            $this->refreshSalesOrder($doc->source_id);
        }
        event(new DocumentChanged($doc->fresh('lines'), 'voided'));
    }

    // ============================================================================================ receiving (PO)

    /**
     * Receive goods against a purchase order: creates a goods received note and the batches (PurchaseService, so the
     * POS can sell them at once). $input: date, warehouse_id, lines: [{line_id, quantity, unit_sale_price, batch_no, expiry_date}].
     */
    public function receive(Document $po, array $input, User $user): Document
    {
        if ($po->type !== 'purchase_order' || ! in_array($po->status, ['draft', 'sent', 'partially_received'], true)) {
            throw ValidationException::withMessages(['document' => 'Only open purchase orders can be received.']);
        }
        $data = validator($input, ['date' => ['required', 'date', 'before_or_equal:today'], 'warehouse_id' => ['nullable', 'integer'], 'lines' => ['required', 'array', 'min:1']])->validate();
        $poLines = $po->lines->keyBy('id');
        $wanted = [];
        $errors = [];
        foreach (array_values($data['lines']) as $i => $row) {
            $line = $poLines[(int) ($row['line_id'] ?? 0)] ?? null;
            $qty = round((float) ($row['quantity'] ?? 0), 4);
            if (! $line || $qty <= 0) {
                continue;
            }
            if ($qty > $line->qty - $line->done_qty + 0.00001) {
                $errors["lines.{$i}.quantity"] = 'Only '.round($line->qty - $line->done_qty, 2).' are still expected.';
                continue;
            }
            $wanted[] = [$i, $line, $qty, $row];
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        if (! $wanted) {
            throw ValidationException::withMessages(['lines' => 'Enter the quantity received for at least one line.']);
        }

        return DB::transaction(function () use ($po, $data, $wanted, $user) {
            $grn = Document::create([
                'type' => 'goods_receipt', 'number' => $this->numbers->next((int) $po->business_id, 'goods_receipt'), 'supplier_id' => $po->supplier_id,
                'warehouse_id' => $po->warehouse_id, 'source_id' => $po->id, 'date' => $data['date'], 'reference' => $po->number, 'status' => 'received',
                'created_by' => $user->id, 'issued_at' => now(),
            ]);
            $purchaseLines = [];
            foreach ($wanted as $n => [$i, $line, $qty, $row]) {
                $unitNet = $line->qty > 0 ? round($line->net_amount / $line->qty, 4) : (float) $line->unit_price;
                DocumentLine::create([
                    'document_id' => $grn->id, 'product_id' => $line->product_id, 'category_id' => $line->category_id, 'source_line_id' => $line->id,
                    'qty' => $qty, 'base_qty' => round($line->base_qty / max($line->qty, 0.0001) * $qty, 4), 'unit_price' => $unitNet,
                    'net_amount' => round($unitNet * $qty, 2), 'line_total' => round($unitNet * $qty, 2), 'position' => $n,
                ]);
                $purchaseLines[] = [
                    'product_id' => $line->product_id, 'category_id' => $line->category_id, 'quantity' => $qty, 'unit_cost_price' => $unitNet,
                    'unit_sale_price' => $row['unit_sale_price'] ?? null, 'batch_no' => $row['batch_no'] ?? null, 'expiry_date' => $row['expiry_date'] ?? null,
                ];
                $line->update(['done_qty' => round($line->done_qty + $qty, 4)]);
            }
            try {
                $created = $this->purchases->create([
                    'supplier' => ['id' => $po->supplier_id], 'invoice_no' => $po->number, 'lines' => $purchaseLines,
                    'warehouse_id' => $data['warehouse_id'] ?? $po->warehouse_id, 'source' => 'po_receipt',
                ]);
            } catch (ValidationException $e) {
                // PurchaseService keys errors "lines.<n>.<field>"; map them back to the receive form's rows.
                $map = [];
                foreach ($e->errors() as $key => $messages) {
                    $map[preg_replace_callback('/^lines\.(\d+)\./', fn ($m) => 'lines.'.$wanted[(int) $m[1]][0].'.', $key)] = $messages;
                }
                throw ValidationException::withMessages($map);
            }
            Purchase::query()->whereIn('id', $created->pluck('id'))->update(['document_id' => $grn->id]);
            $grn->update(['total' => round($grn->lines()->sum('net_amount'), 2), 'subtotal' => round($grn->lines()->sum('net_amount'), 2), 'cost_total' => round($grn->lines()->sum('net_amount'), 2)]);
            $this->refreshPurchaseOrder($po->id);

            return $grn->fresh('lines');
        });
    }

    // ============================================================================================ payments

    /** Record money received (direction in) or paid (out), allocated to open invoices / bills of that party. */
    public function recordPayment(string $direction, array $input, User $user): Payment
    {
        $in = $direction === 'in';
        $data = validator($input, [
            'customer_id' => [$in ? 'required' : 'nullable', 'integer'], 'supplier_id' => [$in ? 'nullable' : 'required', 'integer'],
            'date' => ['required', 'date', 'before_or_equal:today'], 'amount' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'method' => ['required', 'in:'.implode(',', array_keys(Payment::METHODS))], 'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'], 'allocations' => ['nullable', 'array'], 'allocations.*.document_id' => ['required', 'integer'],
            'allocations.*.amount' => ['nullable', 'numeric', 'min:0'],
        ], ['customer_id.required' => 'Pick the customer.', 'supplier_id.required' => 'Pick the supplier.'])->validate();
        $party = $in ? Customer::query()->find($data['customer_id']) : Supplier::query()->find($data['supplier_id']);
        if (! $party) {
            throw ValidationException::withMessages([$in ? 'customer_id' : 'supplier_id' => 'Pick one from your list.']);
        }

        return DB::transaction(function () use ($in, $data, $party, $user) {
            $amount = round((float) $data['amount'], 2);
            $applied = 0.0;
            $plan = [];
            foreach ($data['allocations'] ?? [] as $i => $a) {
                $value = round((float) ($a['amount'] ?? 0), 2);
                if ($value <= 0) {
                    continue;
                }
                $doc = Document::query()->whereKey($a['document_id'])->where('type', $in ? 'sales_invoice' : 'bill')
                    ->where($in ? 'customer_id' : 'supplier_id', $party->id)->whereIn('status', ['unpaid', 'partial'])->lockForUpdate()->first();
                if (! $doc) {
                    throw ValidationException::withMessages(["allocations.{$i}.amount" => 'This document is not open for this '.($in ? 'customer' : 'supplier').'.']);
                }
                if ($value > $doc->balance + 0.004) {
                    throw ValidationException::withMessages(["allocations.{$i}.amount" => "More than the {$doc->number} balance (".number_format($doc->balance, 2).').']);
                }
                $plan[] = [$doc, $value];
                $applied += $value;
            }
            if ($applied > $amount + 0.004) {
                throw ValidationException::withMessages(['amount' => 'The amounts applied add up to more than the payment.']);
            }
            $payment = Payment::create([
                'direction' => $in ? 'in' : 'out', 'number' => $this->numbers->next((int) $party->business_id, $in ? 'payment_receipt' : 'bill_payment'),
                'customer_id' => $in ? $party->id : null, 'supplier_id' => $in ? null : $party->id, 'date' => $data['date'], 'amount' => $amount,
                'unapplied' => round($amount - $applied, 2), 'method' => $data['method'], 'reference' => $data['reference'] ?? null, 'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);
            foreach ($plan as [$doc, $value]) {
                PaymentAllocation::create(['payment_id' => $payment->id, 'document_id' => $doc->id, 'amount' => $value]);
                $doc->update(['paid' => round($doc->paid + $value, 2), 'balance' => round($doc->balance - $value, 2)]);
                $this->refreshStatus($doc);
            }
            event(new PaymentChanged($payment->fresh('allocations'), 'recorded'));

            return $payment;
        });
    }

    /** Delete a payment: what it settled is open again. */
    public function voidPayment(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            foreach ($payment->allocations as $a) {
                $doc = Document::query()->whereKey($a->document_id)->lockForUpdate()->first();
                if ($doc) {
                    $doc->update(['paid' => round($doc->paid - $a->amount, 2), 'balance' => round($doc->balance + $a->amount, 2)]);
                    $this->refreshStatus($doc);
                }
                $a->delete();
            }
            $payment->delete();
            event(new PaymentChanged($payment, 'voided'));
        });
    }

    // ============================================================================================ internals

    private function sourceFor(string $type, $sourceId): ?Document
    {
        $need = ['credit_note' => 'sales_invoice', 'bill' => 'purchase_order', 'vendor_credit' => 'bill'][$type] ?? null;
        if (! $sourceId) {
            if ($need) {
                throw ValidationException::withMessages(['document' => match ($type) {
                    'credit_note' => 'Create a credit note from the invoice it corrects.',
                    'bill' => 'Create a bill from a purchase order after receiving the goods.',
                    default => 'Create a vendor credit from the bill it corrects.',
                }]);
            }

            return null;
        }
        $source = Document::query()->find($sourceId);
        if (! $source || ($need && $source->type !== $need)) {
            throw ValidationException::withMessages(['document' => 'The document this was created from no longer exists.']);
        }

        return $source;
    }

    /** Quantity of a line not yet carried into a document of type $to. */
    private function convertibleQty(Document $from, DocumentLine $l, string $to): float
    {
        return match (true) {
            $from->type === 'purchase_order' => (float) $l->done_qty - $this->billedQty($l->id),
            in_array($from->type, ['sales_order', 'sales_invoice', 'bill'], true) => (float) $l->qty - (float) $l->done_qty,
            default => (float) $l->qty,
        };
    }

    private function billedQty(int $poLineId, ?int $exceptDocId = null): float
    {
        return (float) DocumentLine::query()->where('source_line_id', $poLineId)
            ->whereHas('document', fn ($q) => $q->where('type', 'bill')->where('status', '!=', 'void')->where('status', '!=', 'draft')->when($exceptDocId, fn ($w) => $w->whereKeyNot($exceptDocId)))
            ->sum('qty');
    }

    private function checkLines(string $type, array $rows, ?Document $source, User $user, int $businessId, ?Document $doc): array
    {
        $sales = Document::TYPES[$type]['side'] === 'sales';
        $fixedPrice = in_array($type, ['credit_note', 'vendor_credit'], true);
        $canChangePrice = ! $sales || $user->can('b2b_sales.price_change');
        $sourceLines = $source ? $source->lines->keyBy('id') : collect();
        $out = [];
        $errors = [];
        foreach (array_values($rows) as $i => $row) {
            $v = validator(is_array($row) ? $row : [], [
                'product_id' => ['required', 'integer'], 'category_id' => ['required', 'integer'], 'quantity' => ['required', 'numeric', 'gt:0', 'max:10000000'],
                'unit_price' => ['nullable', 'numeric', 'min:0', 'max:100000000'], 'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'tax_id' => ['nullable', 'integer'], 'description' => ['nullable', 'string', 'max:255'], 'source_line_id' => ['nullable', 'integer'],
            ], [], ['product_id' => 'item', 'category_id' => 'unit', 'unit_price' => 'price']);
            if ($v->fails()) {
                foreach ($v->errors()->toArray() as $k => $m) {
                    $errors["lines.{$i}.{$k}"] = $m;
                }
                continue;
            }
            $d = $v->validated();
            $product = Product::query()->whereKey($d['product_id'])->first();
            if (! $product || $product->is_draft) {
                $errors["lines.{$i}.product_id"] = ['Pick a set-up item from your list.'];
                continue;
            }
            $units = $this->packaging->units($product->id);
            if (! array_key_exists((int) $d['category_id'], $units)) {
                $errors["lines.{$i}.category_id"] = ['Pick one of this item’s units.'];
                continue;
            }
            $src = null;
            if ($source) {
                $src = $sourceLines[(int) ($d['source_line_id'] ?? 0)] ?? null;
                if (in_array($type, ['credit_note', 'bill', 'vendor_credit'], true) && (! $src || (int) $src->product_id !== (int) $product->id || (int) $src->category_id !== (int) $d['category_id'])) {
                    $errors["lines.{$i}.product_id"] = ["Only lines of {$source->number} can be on this document."];
                    continue;
                }
            }
            $qty = round((float) $d['quantity'], 4);
            [, $baseQty] = $this->packaging->toBase($product->id, (int) $d['category_id'], $qty);

            // Price: credits keep the original price; sales lines use the item's price unless the user may change it.
            if ($fixedPrice && $src) {
                $price = (float) $src->unit_price;
                $discount = (float) $src->discount_percent;
                $taxId = $src->tax_id;
            } else {
                $serverPrice = $sales ? $this->salePrice($product, (int) $d['category_id']) : null;
                $price = ($d['unit_price'] ?? null) !== null && $canChangePrice ? (float) $d['unit_price'] : ($serverPrice ?? 0.0);
                if ($sales && $src && ! $canChangePrice) {
                    $price = (float) $src->unit_price; // converted from a quotation / order: its agreed price
                }
                $discount = (float) ($d['discount_percent'] ?? 0);
                $taxId = $d['tax_id'] ?? null;
                if (! $sales && $price <= 0) {
                    $errors["lines.{$i}.unit_price"] = ['Enter the unit cost.'];
                    continue;
                }
                if ($sales && $price <= 0) {
                    $errors["lines.{$i}.unit_price"] = ['This item has no sale price for this unit: enter one.'];
                    continue;
                }
            }
            $tax = $taxId ? Tax::query()->find($taxId) : null;
            if ($taxId && ! $tax) {
                $errors["lines.{$i}.tax_id"] = ['Pick a tax from your list.'];
                continue;
            }
            $gross = round($qty * $price, 2);
            $net = round($gross * (1 - $discount / 100), 2);
            $taxAmount = round($net * (float) ($tax?->rate ?? 0) / 100, 2);
            $out[] = [
                'product_id' => $product->id, 'category_id' => (int) $d['category_id'], 'source_line_id' => $src?->id, 'description' => $d['description'] ?? null,
                'qty' => $qty, 'base_qty' => round((float) $baseQty, 4), 'unit_price' => round($price, 4), 'discount_percent' => round($discount, 2),
                'discount_amount' => round($gross - $net, 2), 'tax_id' => $tax?->id, 'tax_rate' => (float) ($tax?->rate ?? 0), 'tax_amount' => $taxAmount,
                'net_amount' => $net, 'line_total' => round($net + $taxAmount, 2),
            ];
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $out;
    }

    private function salePrice(Product $product, int $categoryId): float
    {
        try {
            $settings = app(SalePreferenceResolver::class)->forProduct($product);

            return round(app(SalePriceCalculator::class)->unitPrice($product->id, $categoryId, $settings), 2);
        } catch (\Throwable) {
            return 0.0;
        }
    }

    private function totals(Document $doc): void
    {
        $lines = $doc->lines()->get();
        $total = round($lines->sum('line_total'), 2);
        $doc->update([
            'subtotal' => round($lines->sum('net_amount') + $lines->sum('discount_amount'), 2), 'discount_total' => round($lines->sum('discount_amount'), 2),
            'tax_total' => round($lines->sum('tax_amount'), 2), 'total' => $total, 'balance' => $doc->status === 'draft' ? $total : $doc->balance,
        ]);
    }

    /** A credit note / vendor credit settles its source document first; anything left stays as credit. */
    public function applyCredit(Document $credit, Document $target): void
    {
        $value = round(min($credit->total, max(0, $target->balance)), 2);
        if ($value > 0) {
            PaymentAllocation::create(['credit_document_id' => $credit->id, 'document_id' => $target->id, 'amount' => $value]);
            $target->update(['paid' => round($target->paid + $value, 2), 'balance' => round($target->balance - $value, 2)]);
            $this->refreshStatus($target);
        }
        $left = round($credit->total - $value, 2);
        $credit->update(['paid' => $value, 'balance' => $left, 'status' => $left > 0 ? 'open_credit' : 'applied']);
    }

    private function refreshStatus(Document $doc): void
    {
        if (! in_array($doc->type, ['sales_invoice', 'bill'], true) || in_array($doc->status, ['draft', 'void'], true)) {
            return;
        }
        $doc->update(['status' => $doc->balance <= 0.004 ? 'paid' : ($doc->paid > 0.004 ? 'partial' : 'unpaid')]);
    }

    private function markSourceDone(Document $invoice): void
    {
        if (! $invoice->source_id) {
            return;
        }
        $source = Document::query()->find($invoice->source_id);
        if ($source?->type === 'sales_order') {
            foreach ($invoice->lines as $line) {
                if ($line->source_line_id) {
                    DocumentLine::query()->whereKey($line->source_line_id)->increment('done_qty', $line->qty);
                }
            }
            $this->refreshSalesOrder($source->id);
        } elseif ($source?->type === 'quotation') {
            $source->update(['status' => 'converted']);
        }
    }

    private function refreshSalesOrder(?int $id): void
    {
        $so = $id ? Document::query()->find($id) : null;
        if ($so && $so->type === 'sales_order' && $so->status !== 'cancelled') {
            $done = $so->lines()->get()->every(fn ($l) => $l->done_qty + 0.00001 >= $l->qty);
            $so->update(['status' => $done ? 'invoiced' : 'open']);
        }
    }

    private function refreshPurchaseOrder(?int $id): void
    {
        $po = $id ? Document::query()->find($id) : null;
        if (! $po || $po->type !== 'purchase_order' || $po->status === 'cancelled') {
            return;
        }
        $lines = $po->lines()->get();
        $received = $lines->sum('done_qty');
        $all = $lines->every(fn ($l) => $l->done_qty + 0.00001 >= $l->qty);
        $po->update(['status' => $received <= 0 ? ($po->issued_at ? 'sent' : 'draft') : ($all ? 'received' : 'partially_received')]);
    }

    private function bumpCounter(int $productId, float $delta): void
    {
        $stock = ProductStock::query()->where('product_id', $productId)->lockForUpdate()->first();
        if ($stock) {
            $stock->current_stock = max(0, round((float) $stock->current_stock + $delta, 6));
            $stock->save();
        } elseif ($delta > 0) {
            ProductStock::create(['product_id' => $productId, 'base_category_id' => $this->packaging->baseCategoryId($productId), 'current_stock' => $delta]);
        }
    }
}
