<?php

namespace App\Central\Entitlement;

use App\Central\Enums\SubscriptionStatus;
use App\Central\Enums\TenantModuleStatus;
use App\Central\Models\Subscription;
use App\Central\Models\Tenant;
use App\Central\Models\TenantModule;
use App\Contracts\ModuleEntitlement;
use App\Support\Modules\ModuleRegistry;
use App\Support\Modules\ModuleState;
use App\Support\Modules\PlanLimits;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * SaaS driver of ModuleEntitlement (TDD §6 "Entitlement", §8 "Dampak ke akses").
 *
 * State of a module:
 *  - inactive  no tenant_modules row, or the row is inactive / installing / failed
 *  - readonly  row is readonly, the row's expires_at has passed, or the
 *              subscription is expired / cancelled (data is kept, ADR-06)
 *  - active    otherwise (trialing, active and past_due subscriptions)
 *
 * A module can be activated when it is core, part of the plan, or an active
 * add-on, and the subscription is trialing / active / past_due.
 *
 * Works on the current tenant, or on the one given to forTenant().
 */
class SubscriptionEntitlement implements ModuleEntitlement
{
    private const USABLE = [SubscriptionStatus::Trialing, SubscriptionStatus::Active, SubscriptionStatus::PastDue];

    private const READ_ONLY = [SubscriptionStatus::Expired, SubscriptionStatus::Cancelled];

    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly ?Tenant $tenant = null,
    ) {}

    public function forTenant(Tenant $tenant): static
    {
        return new static($this->registry, $tenant);
    }

    public function state(string $module): ModuleState
    {
        return $this->snapshot()->state($module);
    }

    public function canActivate(string $module): bool
    {
        return $this->snapshot()->isEntitled($module);
    }

    public function expiresAt(string $module): ?CarbonImmutable
    {
        return $this->snapshot()->expiresAt($module);
    }

    public function limits(): PlanLimits
    {
        return $this->snapshot()->limits;
    }

    public function flush(): void
    {
        EntitlementCache::forget($this->tenant()->getTenantKey());
    }

    public function snapshot(): EntitlementSnapshot
    {
        $tenant = $this->tenant();

        return EntitlementCache::remember($tenant->getTenantKey(), fn () => $this->build($tenant));
    }

    private function build(Tenant $tenant): EntitlementSnapshot
    {
        $subscription = Subscription::with(['plan.modules', 'addons.module'])
            ->where('tenant_id', $tenant->getTenantKey())
            ->latest('id')
            ->first();

        $rows = TenantModule::with('module')->where('tenant_id', $tenant->getTenantKey())->get();
        $now = now();
        $states = [];
        $expires = [];

        foreach ($rows as $row) {
            $code = $row->module->code;
            $expiry = $row->expires_at ?? $subscription?->current_period_end;
            $expires[$code] = $expiry?->toIso8601String();

            $states[$code] = match (true) {
                $row->status === TenantModuleStatus::Readonly => ModuleState::ReadOnly,
                $row->status !== TenantModuleStatus::Active => ModuleState::Inactive,
                $row->expires_at !== null && $row->expires_at->isBefore($now) => ModuleState::ReadOnly,
                $subscription !== null && in_array($subscription->status, self::READ_ONLY, true) => ModuleState::ReadOnly,
                default => ModuleState::Active,
            };
        }

        $entitled = [];

        if ($subscription === null || in_array($subscription->status, self::USABLE, true)) {
            $entitled = array_keys($this->registry->core());

            if ($subscription) {
                $entitled = [
                    ...$entitled,
                    ...$subscription->plan->modules->pluck('code'),
                    ...$subscription->addons
                        ->filter(fn ($addon) => $addon->ended_at === null || $addon->ended_at->isAfter($now))
                        ->pluck('module.code'),
                ];
            }
        }

        $plan = $subscription?->plan;

        return new EntitlementSnapshot(
            states: $states,
            entitled: array_values(array_unique(array_filter($entitled, fn ($code) => $this->registry->has($code)))),
            expiresAt: $expires,
            limits: new PlanLimits($plan?->max_users, $plan?->max_companies, $plan?->max_stores),
        );
    }

    private function tenant(): Tenant
    {
        return $this->tenant ?? tenant() ?? throw new RuntimeException('No tenant: initialize tenancy or use forTenant().');
    }
}
