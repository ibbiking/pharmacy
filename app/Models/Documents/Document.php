<?php

namespace App\Models\Documents;

use App\Models\Customer;
use App\Models\Inventory\Warehouse;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * One B2B document (Roadmap 8–9). TYPES describes each kind: side, label, number series, party, list route, what it can
 * become. Business rules live in App\Services\Documents\DocumentService.
 */
class Document extends Model
{
    use \App\Traits\BelongsToBusiness;
    use \Illuminate\Database\Eloquent\SoftDeletes;

    public const TYPES = [
        'quotation' => ['side' => 'sales', 'label' => 'Quotation', 'plural' => 'Quotations', 'series' => 'quotation', 'slug' => 'quotations', 'icon' => 'file-earmark-text', 'accent' => 'var(--chart-1)'],
        'sales_order' => ['side' => 'sales', 'label' => 'Sales order', 'plural' => 'Sales orders', 'series' => 'sales_order', 'slug' => 'orders', 'icon' => 'clipboard-check', 'accent' => 'var(--chart-6)'],
        'sales_invoice' => ['side' => 'sales', 'label' => 'Invoice', 'plural' => 'Invoices', 'series' => 'sales_invoice', 'slug' => 'invoices', 'icon' => 'receipt-cutoff', 'accent' => 'var(--chart-4)'],
        'credit_note' => ['side' => 'sales', 'label' => 'Credit note', 'plural' => 'Credit notes', 'series' => 'credit_note', 'slug' => 'credit-notes', 'icon' => 'arrow-return-left', 'accent' => 'var(--app-warning)'],
        'purchase_order' => ['side' => 'purchase', 'label' => 'Purchase order', 'plural' => 'Purchase orders', 'series' => 'purchase_order', 'slug' => 'orders', 'icon' => 'cart-plus', 'accent' => 'var(--chart-7)'],
        'goods_receipt' => ['side' => 'purchase', 'label' => 'Goods received note', 'plural' => 'Goods received', 'series' => 'goods_receipt', 'slug' => 'receipts', 'icon' => 'box-arrow-in-down', 'accent' => 'var(--app-success)'],
        'bill' => ['side' => 'purchase', 'label' => 'Bill', 'plural' => 'Bills', 'series' => 'bill', 'slug' => 'bills', 'icon' => 'receipt', 'accent' => 'var(--chart-2)'],
        'vendor_credit' => ['side' => 'purchase', 'label' => 'Vendor credit', 'plural' => 'Vendor credits', 'series' => 'vendor_credit', 'slug' => 'credits', 'icon' => 'arrow-return-right', 'accent' => 'var(--app-warning)'],
    ];

    protected $fillable = [
        'type', 'number', 'customer_id', 'supplier_id', 'warehouse_id', 'source_id', 'date', 'due_date', 'reference', 'status',
        'subtotal', 'discount_total', 'tax_total', 'total', 'paid', 'balance', 'cost_total', 'notes', 'terms', 'created_by', 'issued_at',
        'origin', 'invoice_id', 'purchase_id',
    ];

    protected $casts = [
        'date' => 'date', 'due_date' => 'date', 'issued_at' => 'datetime',
        'subtotal' => 'float', 'discount_total' => 'float', 'tax_total' => 'float', 'total' => 'float', 'paid' => 'float', 'balance' => 'float', 'cost_total' => 'float',
    ];

    public function lines() { return $this->hasMany(DocumentLine::class)->orderBy('position')->orderBy('id'); }
    public function customer() { return $this->belongsTo(Customer::class)->withTrashed(); }
    public function supplier() { return $this->belongsTo(Supplier::class)->withTrashed(); }
    public function warehouse() { return $this->belongsTo(Warehouse::class)->withTrashed(); }
    public function source() { return $this->belongsTo(self::class, 'source_id')->withTrashed(); }
    public function children() { return $this->hasMany(self::class, 'source_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function allocations() { return $this->hasMany(PaymentAllocation::class); }

    public function meta(): array { return self::TYPES[$this->type]; }
    public function typeLabel(): string { return self::TYPES[$this->type]['label'] ?? $this->type; }
    public function isSales(): bool { return (self::TYPES[$this->type]['side'] ?? null) === 'sales'; }
    public function party() { return $this->isSales() ? $this->customer : $this->supplier; }
    public function isOverdue(): bool { return $this->due_date && $this->balance > 0.004 && $this->due_date->lt(today()) && in_array($this->status, ['unpaid', 'partial'], true); }

    /** Created in the background for a POS sale / POS return / purchase (Pharmacy, Retail): not a document screen of its own. */
    public function isLinked(): bool { return $this->origin !== null; }

    /** Where to open it: a linked document opens its POS invoice or purchase; others their document page. */
    public function href(): string
    {
        return match (true) {
            in_array($this->origin, ['pos', 'pos_return'], true) && $this->invoice_id => route('invoices.show', \App\Models\Invoice::query()->whereKey($this->invoice_id)->value('invoice_no') ?? $this->number),
            $this->origin === 'purchase' && $this->purchase_id => route('purchases.show', $this->purchase_id),
            default => route(($this->isSales() ? 'selling' : 'buying').'.docs.show', $this->id),
        };
    }
}
