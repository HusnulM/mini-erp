<?php

namespace App\Contracts;

use App\Support\Modules\ModuleState;
use App\Support\Modules\PlanLimits;
use Carbon\CarbonImmutable;

/**
 * Single answer to "may the current tenant use module X?" (TDD ADR-05).
 *
 * Drivers:
 *  - SubscriptionEntitlement (SaaS)  reads tenant_modules + subscriptions  — Sprint 3
 *  - LicenseEntitlement (on-prem)    reads a signed license file           — later
 *
 * Middleware, menus, Filament canAccess() and scheduled jobs must go through
 * this interface; never check APP_MODE or subscription tables directly.
 */
interface ModuleEntitlement
{
    public function state(string $module): ModuleState;

    public function canActivate(string $module): bool;

    public function expiresAt(string $module): ?CarbonImmutable;

    public function limits(): PlanLimits;
}
