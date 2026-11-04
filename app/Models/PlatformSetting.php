<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** Platform-wide settings (not per business), e.g. the "Free for everyone" launch switch. */
class PlatformSetting extends Model
{
    use \App\Traits\Auditable;

    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['value' => 'json'];

    private const CACHE_PREFIX = 'platform_setting:';

    /**
     * Read a setting. Cached in the application cache (not a static variable) so queue workers and
     * other long-running processes see a change as soon as it is saved.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Cache::rememberForever(self::CACHE_PREFIX.$key, fn () => ['v' => optional(static::find($key))->value]);

        return $value['v'] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget(self::CACHE_PREFIX.$key);
    }

    public static function flushCache(): void
    {
        foreach (static::query()->pluck('key') as $key) {
            Cache::forget(self::CACHE_PREFIX.$key);
        }
    }

    /** Launch pricing: everything free (optionally until a grace date). */
    public static function freeForEveryone(): bool
    {
        if (! (bool) static::get('free_for_everyone', true)) {
            $until = static::get('free_grace_until');

            return $until !== null && now()->lte(\Illuminate\Support\Carbon::parse($until)->endOfDay());
        }

        return true;
    }
}
