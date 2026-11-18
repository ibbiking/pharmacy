<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use \App\Traits\BelongsToBusiness;

    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_name',
        'generic_product_id',
        'company_id',
        'farmula_id',
        'product_type_id',
        'strength_id',
        'sale_price_preference_id',
        'sale_price_including_tax',
        'description',
        'barcode',
        'discount',
        'discount_percent',
        'lock_max_discount',
        'rack',
        'is_draft',
        'min_indicated_qty',
        'min_qty_category_id',
        'lot_selection_mode',
        'allow_price_change',
        'min_price_percent',
        'has_expiry',
    ];

    protected $casts = ['has_expiry' => 'boolean'];

    protected static function booted(): void
    {
        // "Can expire" defaults to the business: on with the Pharmacy Pack, off for shops (the owner changes it per item).
        static::creating(function (Product $product) {
            if (! array_key_exists('has_expiry', $product->getAttributes())) {
                $product->has_expiry = self::expiryDefault($product->business_id ? (int) $product->business_id : null);
            }
        });
    }

    /** Whether a new item of this business can expire unless the owner says otherwise. */
    public static function expiryDefault(?int $businessId): bool
    {
        return $businessId === null || app(\App\Services\Modules\ModuleManager::class)->isActive($businessId, 'pharmacy');
    }

    public function scopeReal($query)
    {
        return $query->where('is_draft', false);
    }

    public function scopeDraft($query)
    {
        return $query->where('is_draft', true);
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function parameters()
    {
        return $this->hasMany(ProductParameter::class);
    }
    public function company()
    {
        return $this->belongsTo(Company::class);
    }


    public function type()
    {
        return $this->belongsTo(ProductType::class, 'product_type_id');
    }

    public function strength()
    {
        return $this->belongsTo(Strength::class, 'strength_id');
    }

    public function stock()
    {
        return $this->hasOne(ProductStock::class, 'product_id');
    }

    public function minQtyCategory()
    {
        return $this->belongsTo(Category::class, 'min_qty_category_id');
    }
}
