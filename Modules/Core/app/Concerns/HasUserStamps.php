<?php

namespace Modules\Core\Concerns;

/** Fills created_by / updated_by with the signed-in tenant user (PRD §55). */
trait HasUserStamps
{
    public static function bootHasUserStamps(): void
    {
        static::creating(function ($model) {
            $id = auth('web')->id();
            $model->created_by ??= $id;
            $model->updated_by ??= $id;
        });

        static::updating(function ($model) {
            if ($id = auth('web')->id()) {
                $model->updated_by = $id;
            }
        });
    }
}
