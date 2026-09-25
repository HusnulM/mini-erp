<?php

namespace App\Central\Entitlement;

/**
 * For central models that change what a tenant may use (tenant_modules,
 * subscriptions, add-ons): any write drops that tenant's cached snapshot,
 * so billing events and module activation take effect immediately.
 */
trait FlushesEntitlementCache
{
    public static function bootFlushesEntitlementCache(): void
    {
        $flush = fn (self $model) => EntitlementCache::forget($model->entitlementTenantId());

        static::saved($flush);
        static::deleted($flush);
    }

    protected function entitlementTenantId(): ?string
    {
        return $this->getAttribute('tenant_id');
    }
}
