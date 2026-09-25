<?php

namespace App\Support;

/**
 * Central routes are registered once per central domain (routes/web.php).
 * The first domain in CENTRAL_DOMAINS is the canonical one and gets plain
 * "central.*" names; the others get "central.{domain}.*".
 *
 * name() picks the variant for the current host, so links stay on the
 * domain the operator is using; outside a central request (queue, CLI,
 * tenant host) it falls back to the canonical domain.
 */
final class CentralRoutes
{
    /** @return list<string> */
    public static function domains(): array
    {
        return config('erp.central_domains');
    }

    public static function primaryDomain(): string
    {
        return self::domains()[0];
    }

    public static function prefix(string $domain): string
    {
        return $domain === self::primaryDomain() ? 'central.' : "central.{$domain}.";
    }

    public static function isCentralHost(?string $host): bool
    {
        return $host !== null && in_array($host, self::domains(), true);
    }

    public static function name(string $name, ?string $host = null): string
    {
        $host ??= app()->bound('request') ? request()->getHost() : null;

        return self::prefix(self::isCentralHost($host) ? $host : self::primaryDomain()).$name;
    }
}
