<?php

namespace App\Traits;

use App\Services\Audit\AuditLogger;

/**
 * Records created / updated / deleted / restored events of a model in audit_logs, with a
 * field-by-field diff for updates. Timestamps and hidden/secret attributes are never logged.
 * Optionally define `protected array $auditExclude = [...]` on the model.
 */
trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(fn ($model) => $model->writeAudit('created', $model->auditSnapshot()));
        static::updated(function ($model) {
            $changes = [];
            foreach ($model->getChanges() as $field => $to) {
                if ($model->isAuditIgnored($field)) {
                    continue;
                }
                $changes[$field] = ['from' => $model->getOriginal($field), 'to' => $to];
            }
            if ($changes) {
                $model->writeAudit('updated', $changes);
            }
        });
        static::deleted(fn ($model) => $model->writeAudit('deleted', $model->auditSnapshot()));
        if (method_exists(static::class, 'restored')) {
            static::restored(fn ($model) => $model->writeAudit('restored'));
        }
    }

    protected function writeAudit(string $action, array $changes = []): void
    {
        $label = $this->getAttribute('name') ?? $this->getAttribute('product_name') ?? $this->getAttribute('slug') ?? $this->getAttribute('module') ?? ('#'.$this->getKey());
        app(AuditLogger::class)->record($action, $this, $changes, class_basename($this)." {$label} {$action}");
    }

    protected function auditSnapshot(): array
    {
        return collect($this->getAttributes())
            ->reject(fn ($v, $k) => $this->isAuditIgnored($k))
            ->map(fn ($v) => ['to' => $v])
            ->all();
    }

    protected function isAuditIgnored(string $field): bool
    {
        return in_array($field, array_merge(
            ['created_at', 'updated_at', 'deleted_at', 'remember_token', 'password'],
            $this->getHidden(),
            property_exists($this, 'auditExclude') ? $this->auditExclude : [],
        ), true);
    }
}
