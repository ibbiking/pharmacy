<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/** Writes audit entries. Never throws: auditing must not break the action being audited. */
class AuditLogger
{
    public function record(string $action, ?Model $subject = null, array $changes = [], ?string $summary = null, array $meta = []): ?AuditLog
    {
        try {
            $businessId = $subject?->getAttribute('business_id')
                ?? session('impersonate_business_id')
                ?? session('business_id');

            return AuditLog::create([
                'business_id' => $businessId,
                'user_id' => auth()->id(),
                'action' => $action,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => is_numeric($subject?->getKey()) ? $subject->getKey() : null,
                'summary' => $summary !== null ? mb_substr($summary, 0, 255) : null,
                'changes' => $changes ?: null,
                'meta' => ($subject && ! is_numeric($subject->getKey()) ? ['key' => $subject->getKey()] : []) + $meta ?: null,
                'ip' => app()->runningInConsole() ? null : request()->ip(),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
