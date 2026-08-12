<?php

namespace App\Models\Currency;

use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A currency a business can select in Business Settings.
 *
 * business_id = null  -> global currency, seeded once, visible to every
 *                        business, editable only by a super-admin.
 * business_id = <id>  -> a currency a business added for itself; visible
 *                        and editable only by that business.
 */
class GlobalCurrency extends Model
{
    use HasFactory;

    protected $table = 'currencies';

    protected $fillable = [
        'business_id',
        'currency_code',
        'name',
        'symbol',
        'exchange_rate',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function isGlobal(): bool
    {
        return $this->business_id === null;
    }

    /**
     * Global currencies plus (if a business id is given) that business's
     * own custom currencies — never another business's custom currencies.
     */
    public function scopeVisibleTo($query, $businessId)
    {
        return $query->where(function ($q) use ($businessId) {
            $q->whereNull('business_id');
            if ($businessId) {
                $q->orWhere('business_id', $businessId);
            }
        });
    }
}
