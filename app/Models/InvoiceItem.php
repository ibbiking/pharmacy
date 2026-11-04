<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceItem extends Model
{
    use \App\Traits\BelongsToBusiness;

    use HasFactory, SoftDeletes;

    protected $table = 'invoice_items';

    protected $fillable = [
        'invoice_id',
        'product_id',
        'name',
        'qty',
        'price',
        'discount_type',
        'discount_value',
        'discount_amount',
        'row_total',
        'category_id',
        'price_before_discount',
        'max_discount_percent',
        'max_discount_amount',
        'base_category_id',
        'base_quantity',
        'base_category_price',
        'sale_preference_slug',
        'is_tax_included',
        'tax_amount',
        'lot_selection_mode',
        'server_unit_price',
        'price_overridden',
        'included_tax_amount',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function returns()
    {
        return $this->hasMany(InvoiceItemReturn::class, 'invoice_item_id');
    }

    /** Lots this line consumed (empty for invoices created before the lot ledger existed). */
    public function lots()
    {
        return $this->hasMany(InvoiceItemLot::class);
    }

    public function taxes()
    {
        return $this->hasMany(InvoiceItemTax::class, 'invoice_item_id');
    }
}
