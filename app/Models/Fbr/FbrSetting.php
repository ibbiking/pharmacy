<?php

namespace App\Models\Fbr;

use App\Models\Business;
use Illuminate\Database\Eloquent\Model;

/**
 * A business's FBR link (docs/FBR_INTEGRATION.md). One row per business. The token is encrypted at rest and never
 * serialised or audited.
 *
 * Status: not_started → details_saved → sandbox_verified (a sandbox test invoice succeeded) → live (a production test
 * succeeded). Changing the regime, NTN, environment or token sends it back to details_saved and switches the business
 * back to local numbering, so FBR numbers are only ever printed while the link is proven.
 */
class FbrSetting extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;
    use \App\Traits\Auditable;

    public const STATUSES = ['not_started', 'details_saved', 'sandbox_verified', 'live'];

    protected $guarded = ['id', 'business_id'];

    protected $hidden = ['token'];

    protected $casts = [
        'token' => 'encrypted',
        'sandbox_verified_at' => 'datetime',
        'live_at' => 'datetime',
        'last_success_at' => 'datetime',
        'last_error_at' => 'datetime',
    ];

    public static function for(int $businessId): self
    {
        return static::query()->firstOrNew(['business_id' => $businessId], ['status' => 'not_started', 'regime' => 'pos', 'environment' => 'sandbox', 'offline_policy' => 'block']);
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function terminals()
    {
        return $this->hasMany(FbrTerminal::class, 'business_id', 'business_id');
    }

    public function hasToken(): bool
    {
        return filled($this->getRawOriginal('token')) || filled($this->attributes['token'] ?? null);
    }

    public function isLive(): bool
    {
        return $this->exists && $this->status === 'live' && $this->environment === 'production';
    }

    /** NTN, a token and at least one terminal for the current environment. */
    public function isComplete(): bool
    {
        return filled($this->ntn) && $this->hasToken() && $this->terminal() !== null;
    }

    /** The POS ID to use: the one linked to the till's warehouse, else the default, else the first (same environment). */
    public function terminal(?int $warehouseId = null): ?FbrTerminal
    {
        $all = FbrTerminal::withoutGlobalScopes()->where('business_id', $this->business_id)->where('environment', $this->environment)
            ->orderByDesc('is_default')->orderBy('id')->get();

        return ($warehouseId ? $all->firstWhere('warehouse_id', $warehouseId) : null) ?? $all->first();
    }
}
