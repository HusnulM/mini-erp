<?php

namespace Modules\Core\Concerns;

use Modules\Core\Models\AuditLog;

/**
 * Writes every create / update / delete / restore of the model to audit_logs
 * (PRD §49). Hidden attributes (passwords, secrets), timestamps and the
 * columns in $auditExclude are never logged; an update that only touches
 * excluded columns is not logged at all.
 *
 * @property list<string> $auditExclude
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($model) => AuditLog::record($model, 'created', [], $model->auditableValues($model->getAttributes())));

        static::updated(function ($model) {
            $new = $model->auditableValues($model->getChanges());

            if ($new !== []) {
                $old = array_intersect_key($model->getOriginal(), $new);
                AuditLog::record($model, 'updated', $model->auditableValues($old), $new);
            }
        });

        static::deleted(fn ($model) => AuditLog::record($model, 'deleted', $model->auditableValues($model->getAttributes()), []));

        if (method_exists(static::class, 'restored')) {
            static::restored(fn ($model) => AuditLog::record($model, 'restored', [], ['deleted_at' => null]));
        }
    }

    /** @param  array<string, mixed>  $values */
    public function auditableValues(array $values): array
    {
        $skip = [
            ...$this->getHidden(),
            ...($this->auditExclude ?? []),
            'created_at', 'updated_at', 'created_by', 'updated_by',
        ];

        return array_map(
            fn ($v) => $v instanceof \BackedEnum ? $v->value : ($v instanceof \DateTimeInterface ? $v->format(DATE_ATOM) : $v),
            array_diff_key($values, array_flip($skip))
        );
    }
}
