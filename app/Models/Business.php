<?php

namespace App\Models;

use App\Models\Currency\GlobalCurrency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Business extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    use \App\Traits\Auditable;

    use HasFactory;

    /** Same default as the column, so a new model knows its type before it is reloaded. */
    protected $attributes = ['business_type' => 'pharmacy'];

    protected $fillable = [
        'name', 'business_type', 'logo', 'brand_color', 'created_by', 'address', 'phone', 'note',
        'invoice_source', 'fbr_business_name', 'fbr_ntn', 'fbr_strn',
        'fbr_pos_registration_no', 'fbr_environment', 'fbr_linked_at',
        'currency_id',
    ];

    protected $hidden = ['fbr_api_token'];

    protected $casts = [
        'fbr_linked_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Every business gets its business-wide preference rows the moment it exists
        // (sale price, lot selection FEFO/FIFO, POS price-change and discount rules).
        static::created(function (Business $business) {
            app(\App\Services\Business\BusinessPreferenceDefaults::class)->ensure($business->id);
            app(\App\Services\Numbering\NumberSeriesService::class)->ensureDefaults($business->id);
            app(\App\Services\Access\PermissionService::class)->ensureSystemRoles($business->id);

            // Switch on the default modules for its type (free at launch; the create-business wizard
            // can add or remove modules afterwards).
            // When launch pricing has ended, paid modules come from a subscription instead (Billing).
            // The type's locked modules are part of the type itself, so they are on even after launch pricing.
            $modules = app(\App\Services\Modules\ModuleManager::class);
            $list = \App\Models\PlatformSetting::freeForEveryone()
                ? $modules->bundleFor($business->business_type)
                : $modules->lockedFor($business->business_type);
            foreach ($list as $module) {
                if ($module !== 'core' && ($modules->definition($module)['available'] ?? true)) {
                    $modules->activate($business, $module, \App\Models\PlatformSetting::freeForEveryone() ? 'free' : 'type', auth()->id());
                }
            }
        });

        // One business = one business type, chosen once (owner's rule 2026-11-19). Another type = a new business.
        static::updating(function (Business $business) {
            if ($business->isDirty('business_type') && $business->getOriginal('business_type') !== null) {
                throw new \DomainException('The business type cannot be changed. To work as another type, create a new business.');
            }
        });
    }

    public function subscriptions()
    {
        return $this->hasMany(\App\Models\Billing\Subscription::class);
    }

    public function modules()
    {
        return $this->hasMany(BusinessModule::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot('role', 'status')->withTimestamps();
    }

    public function currency()
    {
        return $this->belongsTo(GlobalCurrency::class, 'currency_id');
    }

    /**
     * The symbol to print/display everywhere prices already show a
     * currency symbol for this business. Falls back to the app-wide
     * `app_currency` setting when the business hasn't picked one yet.
     */
    public function currencySymbol(): string
    {
        return $this->currency->symbol ?? settings('app_currency', 'Rs');
    }

    public function isFbrInvoicing(): bool
    {
        return $this->invoice_source === 'fbr';
    }

    public function hasFbrCredentials(): bool
    {
        return filled($this->fbr_ntn)
            && filled($this->fbr_pos_registration_no)
            && filled($this->fbr_api_token);
    }

    public function setFbrApiTokenAttribute($value)
    {
        $this->attributes['fbr_api_token'] = filled($value) ? Crypt::encryptString($value) : null;
    }

    public function getFbrApiTokenAttribute($value)
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return null;
        }
    }
}
