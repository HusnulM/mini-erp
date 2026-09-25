<?php

namespace App\Central\Registration;

use App\Central\Enums\BillingCycle;
use App\Central\Enums\ModuleSource;
use App\Central\Enums\SubscriptionStatus;
use App\Central\Enums\TenantModuleStatus;
use App\Central\Enums\TenantStatus;
use App\Central\Models\Module;
use App\Central\Models\Plan;
use App\Central\Models\Subscription;
use App\Central\Models\Tenant;
use App\Central\Models\TenantModule;
use App\Central\Provisioning\ProvisioningRunner;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Email verified → trial subscription + entitled modules → ProvisionTenant.
 *
 * Idempotent: a second click on the link finds the tenant already verified
 * and does nothing. Paid plans without a trial go through the payment
 * gateway in Sprint 5; until then every registration starts as a trial.
 */
class VerifyRegistration
{
    public function __construct(
        private readonly ModuleRegistry $modules,
        private readonly ProvisioningRunner $runner,
    ) {}

    public function __invoke(Tenant $tenant): void
    {
        $run = DB::connection('central')->transaction(function () use ($tenant) {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->getKey());

            if ($tenant->email_verified_at !== null || $tenant->status !== TenantStatus::Pending) {
                return null;
            }

            $registration = $tenant->registration();
            $plan = Plan::with('modules')->findOrFail($registration['plan_id']);
            $cycle = BillingCycle::from($registration['billing_cycle']);

            $subscription = $this->createTrial($tenant, $plan, $cycle);
            $this->entitleModules($tenant, $plan);

            $tenant->forceFill([
                'email_verified_at' => now(),
                'status' => TenantStatus::Provisioning,
                'trial_ends_at' => $subscription->current_period_end,
            ])->save();

            return $this->runner->createRun($tenant);
        });

        if ($run) {
            $this->runner->dispatch($run);
        }
    }

    private function createTrial(Tenant $tenant, Plan $plan, BillingCycle $cycle): Subscription
    {
        $start = now();

        return Subscription::firstOrCreate(['tenant_id' => $tenant->id], [
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Trialing,
            'billing_cycle' => $cycle,
            'current_period_start' => $start,
            'current_period_end' => $start->copy()->addDays($plan->trial_days),
            'auto_renew' => true,
        ]);
    }

    /**
     * One tenant_modules row per plan module plus every dependency, in
     * install order. Status `installing` until the install_modules step.
     */
    private function entitleModules(Tenant $tenant, Plan $plan): void
    {
        $codes = $plan->modules->pluck('code')
            ->merge(config('erp.core_modules'))
            ->filter(fn (string $code) => $this->modules->has($code))
            ->unique()
            ->values()
            ->all();

        $ids = Module::whereIn('code', $codes = $this->modules->withDependencies($codes))->pluck('id', 'code');

        foreach ($codes as $code) {
            TenantModule::firstOrCreate(
                ['tenant_id' => $tenant->id, 'module_id' => $ids[$code]
                    ?? throw new RuntimeException("Module [{$code}] is not in the central catalog; run `php artisan modules:sync`.")],
                [
                    'status' => TenantModuleStatus::Installing,
                    'source' => $this->modules->get($code)->isCore ? ModuleSource::Core : ModuleSource::Plan,
                ],
            );
        }
    }
}
