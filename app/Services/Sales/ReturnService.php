<?php

namespace App\Services\Sales;

use App\Models\BaseStockSalePrice;
use App\Models\Business;
use App\Models\Invoice;
use App\Models\InvoiceHistory;
use App\Models\InvoiceItem;
use App\Models\InvoiceItemReturn;
use App\Models\ProductParameter;
use App\Models\ProductPreference;
use App\Models\Preference;
use App\Models\ProductStock;
use App\Models\ReturnHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Returns (Roadmap 5.8a). One place for the rules:
 *  - the invoice is LOCKED for the return (I-6), quantities are checked BEFORE a return number is taken (I-4);
 *  - quantities may be decimals (half a strip) and never exceed what is left on the line;
 *  - cash refund per line = price × qty − its share of the line discount − its share of the invoice discount
 *    + its share of the additive sale tax. GST (disclosure) and tax inside the price are never refunded on top;
 *  - stock goes back into the exact lots the line sold from (invoice_item_lots), older invoices by price;
 *  - every return row keeps a snapshot (price, refund_amount, taxes) so receipts and reprints come from the database.
 */
class ReturnService
{
    public function __construct(private readonly PackagingService $packaging)
    {
    }

    /** What a return of these quantities would refund (read-only, for the dialog). @param array<int, float> $qtys itemId => qty */
    public function preview(Invoice $invoice, array $qtys): array
    {
        $invoice->loadMissing('items.returns', 'items.taxes');
        $lines = [];
        $errors = [];
        foreach ($qtys as $itemId => $qty) {
            $item = $invoice->items->firstWhere('id', (int) $itemId);
            $qty = round((float) $qty, 4);
            if (! $item || $qty <= 0) {
                continue;
            }
            $left = $this->left($item);
            if ($qty > $left + 1e-6) {
                $errors["qty.{$itemId}"] = "Only {$left} of {$item->name} can still be returned.";
                continue;
            }
            $lines[] = $this->amounts($invoice, $item, $qty, $left);
        }

        return ['lines' => $lines, 'refund' => round(array_sum(array_column($lines, 'refund')), 2), 'errors' => $errors];
    }

    /**
     * Return quantities of an invoice's lines. @param array<int, float>|null $qtys itemId => qty; null = everything left
     *
     * @return array{return_no: string, refund: float}
     */
    public function return(Invoice $invoice, ?array $qtys, ?string $reason, ?int $userId): array
    {
        $reason = $reason !== null ? mb_substr(trim($reason), 0, 255) : null;

        return DB::transaction(function () use ($invoice, $qtys, $reason, $userId) {
            /** @var Invoice $invoice */
            $invoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail(); // I-6
            $invoice->load('items.returns', 'items.taxes');
            $full = $qtys === null;
            $qtys ??= $invoice->items->mapWithKeys(fn ($i) => [$i->id => $this->left($i)])->all();

            // Check everything first (I-4): nothing is written, no number is taken, when any line is wrong.
            $plan = [];
            $errors = [];
            foreach ($qtys as $itemId => $qty) {
                $qty = round((float) $qty, 4);
                if ($qty <= 0) {
                    continue;
                }
                $item = $invoice->items->firstWhere('id', (int) $itemId);
                if (! $item) {
                    $errors["qty.{$itemId}"] = 'This line is not on the invoice.';
                    continue;
                }
                $left = $this->left($item);
                if ($qty > $left + 1e-6) {
                    $errors["qty.{$itemId}"] = $left > 0 ? "Only {$left} of {$item->name} can still be returned." : "{$item->name} was already fully returned.";
                    continue;
                }
                $plan[] = [$item, $qty, $left];
            }
            if ($errors) {
                throw ValidationException::withMessages($errors);
            }
            if ($plan === []) {
                throw ValidationException::withMessages(['qty' => $full ? 'Everything on this invoice was already returned.' : 'Enter a quantity to return.']); // I-3
            }

            $returnNo = $this->number($invoice, $full, $plan);
            $refund = 0.0;
            foreach ($plan as [$item, $qty, $left]) {
                $refund += $this->apply($invoice, $item, $qty, $left, $returnNo, $reason, $userId);
            }
            $this->recalculate($invoice);

            if ($full) {
                InvoiceHistory::create(['invoice_id' => $invoice->id, 'action' => 'Fully Returned', 'description' => "Everything returned (Return No: {$returnNo}).", 'user_id' => $userId]);
                ReturnHistory::create(['return_no' => $returnNo, 'invoice_id' => $invoice->id, 'action' => 'Fully Returned', 'description' => 'Entire invoice was returned.', 'user_id' => $userId]);
            }

            return ['return_no' => $returnNo, 'refund' => round($refund, 2)];
        });
    }

    /** Quantity of a line not returned yet. */
    public function left(InvoiceItem $item): float
    {
        return round(max(0, (float) $item->qty - (float) $item->returns->sum('qty_returned')), 4);
    }

    // ------------------------------------------------------------------------------------------------ internals

    /** Refund parts for $qty of a line that still has $left (shares are taken from what is left, so repeats add up). */
    private function amounts(Invoice $invoice, InvoiceItem $item, float $qty, float $left): array
    {
        $share = $left > 0 ? $qty / $left : 0;
        $gross = round((float) $item->price * $qty, 4);
        $lineDiscount = (float) $item->discount_amount * $share;
        $net = $gross - $lineDiscount;
        $invoiceShare = (float) $invoice->total > 0 ? $net / (float) $invoice->total : 0;
        $invoiceDiscount = (float) $invoice->invoice_discount_amount * $invoiceShare;
        $taxes = [];
        $additive = 0.0;
        foreach ($item->taxes as $t) {
            $amount = (float) $t->amount * $share;
            $kind = $t->tax_id === null ? 'gst' : ($t->is_included ? 'included' : 'additive');
            $taxes[] = ['tax_id' => $t->tax_id, 'name' => $t->name, 'rate' => (float) $t->rate, 'amount' => round($amount, 4), 'kind' => $kind, 'model' => $t];
            if ($kind === 'additive') {
                $additive += $amount;
            }
        }

        return [
            'item_id' => $item->id, 'name' => $item->name, 'qty' => $qty, 'price' => (float) $item->price, 'gross' => round($gross, 2),
            'line_discount' => round($lineDiscount, 4), 'invoice_discount' => round($invoiceDiscount, 4), 'additive_tax' => round($additive, 4),
            'refund' => round($net - $invoiceDiscount + $additive, 2), 'taxes' => $taxes,
        ];
    }

    private function apply(Invoice $invoice, InvoiceItem $item, float $qty, float $left, string $returnNo, ?string $reason, ?int $userId): float
    {
        $a = $this->amounts($invoice, $item, $qty, $left);

        InvoiceItemReturn::create([
            'return_no' => $returnNo, 'invoice_id' => $invoice->id, 'invoice_item_id' => $item->id, 'product_id' => $item->product_id,
            'qty_returned' => $qty, 'price' => $item->price, 'unit_discount_deducted' => round($a['line_discount'], 2),
            'global_discount_clawback' => round($a['invoice_discount'], 2), 'refund_amount' => $a['refund'],
            'taxes' => array_map(fn ($t) => array_diff_key($t, ['model' => 1]) + ['amount' => round($t['amount'], 2)], $a['taxes']),
            'reason' => $reason, 'handled_by' => $userId,
        ]);
        InvoiceHistory::create(['invoice_id' => $invoice->id, 'action' => 'Item Returned', 'description' => "Returned {$qty} × {$item->name} (Return No: {$returnNo}).", 'user_id' => $userId]);
        ReturnHistory::create(['return_no' => $returnNo, 'invoice_id' => $invoice->id, 'action' => 'Item Returned', 'description' => "Returned {$qty} × {$item->name}.", 'user_id' => $userId]);

        // Stock back into the lots it came from, and the derived counter.
        $baseQty = $this->packaging->toBase((int) $item->product_id, $item->category_id ? (int) $item->category_id : null, $qty)[1];
        $this->restoreStockBySalePreference($item, $baseQty);
        if ($stock = ProductStock::query()->where('product_id', $item->product_id)->lockForUpdate()->first()) {
            $stock->current_stock = (float) $stock->current_stock + $baseQty;
            $stock->save();
        }

        // What is left on the invoice and the line (tax split exactly as at sale time).
        $gst = $included = $additive = 0.0;
        foreach ($a['taxes'] as $t) {
            $t['model']->amount = max(0, (float) $t['model']->amount - $t['amount']);
            $t['model']->save();
            match ($t['kind']) { 'gst' => $gst += $t['amount'], 'included' => $included += $t['amount'], default => $additive += $t['amount'] };
        }
        $rowPart = $a['gross'] - $a['line_discount'];
        $item->discount_amount = max(0, (float) $item->discount_amount - $a['line_discount']);
        $item->row_total = (float) $item->row_total - $rowPart;
        $item->tax_amount = max(0, (float) $item->tax_amount - $gst);
        $item->included_tax_amount = max(0, (float) $item->included_tax_amount - $included);
        $item->save();
        $item->load('returns');

        $invoice->invoice_discount_amount = max(0, (float) $invoice->invoice_discount_amount - $a['invoice_discount']);
        $invoice->tax_amount = max(0, (float) $invoice->tax_amount - $additive);
        $invoice->included_tax_amount = max(0, (float) $invoice->included_tax_amount - $included);
        $invoice->subtotal = (float) $invoice->subtotal - $a['gross'];
        $invoice->discount = (float) $invoice->discount - $a['line_discount'];
        $invoice->total = (float) $invoice->total - $rowPart;

        return $a['refund'];
    }

    private function recalculate(Invoice $invoice): void
    {
        $invoice->grand_total = max(0, round((float) $invoice->total - (float) $invoice->invoice_discount_amount + (float) $invoice->tax_amount, 2));
        if ((float) $invoice->total <= 0.004) {
            $invoice->fully_returned = true;
            $invoice->invoice_discount_amount = 0;
            $invoice->tax_amount = 0;
            $invoice->grand_total = 0;
        }
        $invoice->save();
    }

    /** Local per-business series, or an FBR credit-note number (taken only after every check passed). */
    private function number(Invoice $invoice, bool $full, array $plan): string
    {
        $business = Business::find($invoice->business_id);
        if ($business && $business->isFbrInvoicing()) {
            return app(\App\Services\FbrInvoiceService::class)->generateCreditNoteNumber($business, [
                'originalInvoiceNo' => $invoice->invoice_no, 'invoiceDate' => optional($invoice->created_at)->toDateTimeString(),
                'returnType' => $full ? 'full_invoice' : 'items', 'returnedQty' => array_sum(array_column($plan, 1)),
            ]);
        }

        return app(\App\Services\Numbering\NumberSeriesService::class)->next((int) $invoice->business_id, 'return');
    }

    /** Put the stock back (moved unchanged from InvoiceController): exact lots from the ledger, old invoices by price. */
    private function restoreStockBySalePreference($item, $baseReturnQty)
    {
        // Invoices saved since the lot ledger exists: put stock back into the exact lots this line
        // consumed (most recently consumed first). Older invoices fall back to the price-matching
        // heuristic below.
        $lotRecords = $item->lots()->orderByDesc('id')->get();
        if ($lotRecords->isNotEmpty()) {
            $remainingToRestore = (float) $baseReturnQty;

            foreach ($lotRecords as $record) {
                if (round($remainingToRestore, 4) <= 0) break;

                $restorable = $record->netBaseQty();
                if ($restorable <= 0) continue;

                $restore = min($restorable, $remainingToRestore);
                $lot = BaseStockSalePrice::withoutGlobalScopes()->lockForUpdate()->find($record->base_stock_sale_price_id);
                if ($lot) {
                    $lot->remaining_base_stock = (float) $lot->remaining_base_stock + $restore;
                    $lot->save();
                }

                $record->returned_base_qty = $record->returned_base_qty + $restore;
                $record->save();
                $remainingToRestore -= $restore;
            }

            if (round($remainingToRestore, 4) <= 0) {
                return;
            }
            $baseReturnQty = $remainingToRestore; // should not happen; fall through for safety
        }

        $targetPrice = round((float)$item->base_category_price, 4);
        $preferenceSlug = $item->sale_preference_slug ?? 'static-price';

        // Fetch all batches for this product and base category
        // Order by ID DESC to restore stock to the most recently active batches first
        $stockRows = BaseStockSalePrice::where('product_id', $item->product_id)
            ->where('base_category_id', $item->base_category_id)
            ->orderBy('id', 'desc')
            ->get();

        $matchingRows = [];

        if ($preferenceSlug === 'stock-wise-price') {
            foreach ($stockRows as $row) {
                $rowPrice = $row->base_category_unit_sale_price;
                if ($item->is_tax_included) {
                    $rowPrice += ($row->base_category_unit_sale_tax_price ?? 0);
                }
                
                // Allow for a small float precision drift
                if (abs($rowPrice - $targetPrice) < 0.001) {
                    $matchingRows[] = $row;
                }
            }
        } else {
            // Not stock-wise-price, we do NOT force match the price.
            $matchingRows = $stockRows->all();
        }

        // If no exactly matching price is found (for stock-wise-price), fallback to all rows for this product
        if (empty($matchingRows)) {
            $matchingRows = $stockRows->all();
        }

        $remainingToRestore = $baseReturnQty;

        // 1) Distribute the returned quantity within the base_stock limit sequentially over EXACT matching rows FIRST
        foreach ($matchingRows as $baseStock) {
            if ($remainingToRestore <= 0) break;

            $spaceAvailable = $baseStock->base_stock - $baseStock->remaining_base_stock;
            if ($spaceAvailable > 0) {
                $restoreAmount = min($spaceAvailable, $remainingToRestore);
                $baseStock->remaining_base_stock += $restoreAmount;
                $baseStock->save();
                
                $remainingToRestore -= $restoreAmount;
            }
        }

        // 2) If there is still remaining quantity, fallback to ALL available rows for this product to prevent overflow 
        if ($remainingToRestore > 0 && !$stockRows->isEmpty()) {
            foreach ($stockRows as $baseStock) {
                if ($remainingToRestore <= 0) break;

                $spaceAvailable = $baseStock->base_stock - $baseStock->remaining_base_stock;
                if ($spaceAvailable > 0) {
                    $restoreAmount = min($spaceAvailable, $remainingToRestore);
                    $baseStock->remaining_base_stock += $restoreAmount;
                    $baseStock->save();
                    
                    $remainingToRestore -= $restoreAmount;
                }
            }
        }

        // 3) ONLY if it STILL exceeds ALL available capacity in the entire system, forcefully dump into the newest batch
        if ($remainingToRestore > 0 && !$stockRows->isEmpty()) {
            $baseStock = $stockRows->first();
            $baseStock->remaining_base_stock += $remainingToRestore;
            $baseStock->save();
        }
    }
}
