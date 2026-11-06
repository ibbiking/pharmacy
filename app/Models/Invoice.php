<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use \App\Traits\BelongsToBusiness;

    use HasFactory,SoftDeletes;

    protected $table = 'invoices';

    protected $fillable = [
        'invoice_no',
        'subtotal',
        'discount',
        'total',
        'invoice_discount_type',
        'invoice_discount_value',
        'invoice_discount_amount',
        'grand_total',
        'cash_received',
        'change_return',
        'tax_amount',
        'included_tax_amount',
    ];

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function histories()
    {
        return $this->hasMany(InvoiceHistory::class);
    }

    public function returnHistories()
    {
        return $this->hasMany(ReturnHistory::class);
    }

    /**
     * Short, human-friendly local invoice number derived from this row's own
     * auto-increment id (globally unique, no locking/race risk). Call after
     * the row has been inserted with a placeholder invoice_no.
     */
    /** Give this invoice the next number of its business's invoice series (Roadmap 4g). */
    public function assignLocalInvoiceNo(): string
    {
        $this->invoice_no = app(\App\Services\Numbering\NumberSeriesService::class)->next((int) $this->business_id, 'invoice');
        $this->save();

        return $this->invoice_no;
    }

    /**
     * Split tax breakdown for this invoice, consolidated across all its
     * items and grouped by tax (name + tax_id) — e.g. one "GST" row and one
     * "Sales Tax" row summed from however many lines/batches contributed to
     * each, rather than one opaque lump total. Backed by the persisted
     * invoice_item_taxes rows (see InvoiceItemTax), so a reprint months
     * later shows exactly the same split that was computed at sale time.
     *
     * Expects items.taxes to already be eager-loaded; falls back to lazy
     * loading if not.
     *
     * @return array<int, array{tax_id: ?int, name: string, rate: float, amount: float}>
     */
    public function consolidatedTaxBreakdown(bool $withIncludedTax = false): array
    {
        $buckets = [];

        foreach ($this->items as $item) {
            foreach ($item->taxes as $tax) {
                // Tax included inside the price (decision D3) is owner-only: hidden from customer
                // receipts unless explicitly requested (owner reports).
                if ($tax->is_included && ! $withIncludedTax) {
                    continue;
                }
                $key = ($tax->is_included ? 'in:' : '') . ($tax->tax_id ?? ('name:' . $tax->name));

                if (!isset($buckets[$key])) {
                    $buckets[$key] = [
                        'tax_id' => $tax->tax_id,
                        'is_included' => (bool) $tax->is_included,
                        'name'   => $tax->name,
                        'amount' => 0,
                        'taxable_base' => 0,
                    ];
                }

                $buckets[$key]['amount'] += (float) $tax->amount;
                $buckets[$key]['taxable_base'] += (float) $tax->amount > 0 && $tax->rate > 0
                    ? ($tax->amount / ($tax->rate / 100))
                    : 0;
            }
        }

        foreach ($buckets as &$bucket) {
            $bucket['rate'] = $bucket['taxable_base'] > 0
                ? ($bucket['amount'] / $bucket['taxable_base']) * 100
                : 0;
            unset($bucket['taxable_base']);
        }

        return array_values($buckets);
    }

    /**
     * Cosmetic preview of the next local invoice number, for display before
     * an invoice is actually saved (e.g. the live POS receipt). Not reserved,
     * so the real number assigned at save time may differ slightly if another
     * sale is completed in between.
     */
    /** Cosmetic preview of the business's next invoice number (not reserved). */
    public static function previewNextLocalInvoiceNo(?int $businessId = null): string
    {
        $businessId ??= (int) (session('impersonate_business_id') ?? session('business_id'));

        return app(\App\Services\Numbering\NumberSeriesService::class)->peek($businessId, 'invoice');
    }
}