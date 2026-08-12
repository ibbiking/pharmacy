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
    public function assignLocalInvoiceNo(): string
    {
        $this->invoice_no = 'INV-' . str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
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
    public function consolidatedTaxBreakdown(): array
    {
        $buckets = [];

        foreach ($this->items as $item) {
            foreach ($item->taxes as $tax) {
                $key = $tax->tax_id ?? ('name:' . $tax->name);

                if (!isset($buckets[$key])) {
                    $buckets[$key] = [
                        'tax_id' => $tax->tax_id,
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
    public static function previewNextLocalInvoiceNo(): string
    {
        $nextId = ((int) \Illuminate\Support\Facades\DB::table('invoices')->max('id')) + 1;

        return 'INV-' . str_pad((string) $nextId, 6, '0', STR_PAD_LEFT);
    }
}