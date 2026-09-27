<?php

namespace Modules\Core\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Modules\Core\Services\DataScope;

/**
 * Restricts queries to what the current tenant user may see. No
 * restriction without a signed-in user (jobs, console) or for SUPER ADMIN.
 * A user without any scope row sees nothing (deny by default).
 */
class OrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $allowed = app(DataScope::class)->forCurrentUser();

        if ($allowed === null) {
            return;
        }

        $builder->where(function (Builder $q) use ($allowed, $model) {
            $any = false;

            foreach ($model->dataScopeColumns() as $type => $column) {
                if ($type === 'own' && $allowed['own'] !== null) {
                    $q->orWhere($model->qualifyColumn($column), $allowed['own']);
                    $any = true;
                } elseif ($type !== 'own' && ! empty($allowed[$type])) {
                    $q->orWhereIn($model->qualifyColumn($column), $allowed[$type]);
                    $any = true;
                }
            }

            if (! $any) {
                $q->whereRaw('1 = 0');
            }
        });
    }
}
