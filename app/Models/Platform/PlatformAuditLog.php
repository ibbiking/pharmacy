<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;

/** Append-only log of what platform admins did (opened a business, switched / blocked a module, managed admins). */
class PlatformAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'platform_audit_logs';

    protected $fillable = ['platform_admin_id', 'action', 'business_id', 'details', 'ip'];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    public static function record(string $action, ?int $businessId = null, array $details = []): void
    {
        static::query()->create([
            'platform_admin_id' => auth('platform')->id(),
            'action' => $action,
            'business_id' => $businessId,
            'details' => $details ?: null,
            'ip' => request()?->ip(),
        ]);
    }
}
