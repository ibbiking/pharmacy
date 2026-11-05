<?php

namespace App\Models\Billing;

use App\Models\Business;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A business's Stripe subscription (plan or custom set of modules). Roadmap step 4e. */
class Subscription extends Model
{
    use SoftDeletes;
    use \App\Traits\Auditable;

    /** Statuses that give access to the paid modules. past_due keeps access while Stripe retries the card (grace). */
    public const ENTITLED = ['trialing', 'active', 'past_due'];

    /** Statuses that will not come back: the business must subscribe again. */
    public const ENDED = ['canceled', 'unpaid', 'incomplete_expired'];

    protected $guarded = [];

    protected $casts = [
        'modules' => 'array',
        'items' => 'array',
        'amount' => 'float',
        'trial_ends_at' => 'datetime',
        'current_period_end' => 'datetime',
        'ends_at' => 'datetime',
        'cancel_at_period_end' => 'boolean',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class)->withTrashed();
    }

    public function invoices()
    {
        return $this->hasMany(BillingInvoice::class);
    }

    public function isEntitled(): bool
    {
        return in_array($this->status, self::ENTITLED, true);
    }

    public function isEnded(): bool
    {
        return in_array($this->status, self::ENDED, true);
    }

    /** The subscription a business is using now: the newest entitled one, else the newest that isn't over. */
    public static function currentFor(int $businessId): ?self
    {
        // An entitled subscription wins over a newer, unfinished checkout.
        return static::query()->where('business_id', $businessId)->whereIn('status', self::ENTITLED)->latest('id')->first()
            ?? static::query()->where('business_id', $businessId)->whereNotIn('status', self::ENDED)->latest('id')->first();
    }
}
