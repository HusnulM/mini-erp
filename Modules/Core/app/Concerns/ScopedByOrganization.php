<?php

namespace Modules\Core\Concerns;

use Modules\Core\Scopes\OrganizationScope;

/**
 * Data scope (PRD §8): the model is filtered to the organization units in
 * the signed-in user's user_scopes. The model says which column holds each
 * scope type, e.g. ['company' => 'company_id', 'branch' => 'id',
 * 'own' => 'created_by'].
 */
trait ScopedByOrganization
{
    public static function bootScopedByOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);
    }

    /** @return array<string, string> scope type => column */
    abstract public function dataScopeColumns(): array;
}
