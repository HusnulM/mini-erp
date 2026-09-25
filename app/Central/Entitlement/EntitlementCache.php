<?php

namespace App\Central\Entitlement;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Per-tenant entitlement snapshot, cached for 5 minutes (TDD §6).
 *
 * Cache::store() returns the untagged repository in both contexts (stancl's
 * tenant cache manager only tags magic calls), so the same key is used when
 * a tenant request reads it and when the central app flushes it.
 */
class EntitlementCache
{
    public const TTL = 300;

    /** @param  Closure(): EntitlementSnapshot  $build */
    public static function remember(string $tenantId, Closure $build): EntitlementSnapshot
    {
        return EntitlementSnapshot::fromArray(
            Cache::store()->remember(self::key($tenantId), self::TTL, fn () => $build()->toArray())
        );
    }

    public static function forget(?string $tenantId): void
    {
        if ($tenantId) {
            Cache::store()->forget(self::key($tenantId));
        }
    }

    private static function key(string $tenantId): string
    {
        return "erp:entitlement:{$tenantId}";
    }
}
