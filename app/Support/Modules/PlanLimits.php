<?php

namespace App\Support\Modules;

/**
 * Quantity limits of the tenant's plan. null = unlimited.
 */
final readonly class PlanLimits
{
    public function __construct(
        public ?int $users = null,
        public ?int $companies = null,
        public ?int $stores = null,
    ) {}

    public function allows(string $resource, int $currentCount): bool
    {
        if (! in_array($resource, ['users', 'companies', 'stores'], true)) {
            throw new \InvalidArgumentException("Unknown plan limit [{$resource}].");
        }

        $limit = $this->{$resource};

        return $limit === null || $currentCount < $limit;
    }
}
