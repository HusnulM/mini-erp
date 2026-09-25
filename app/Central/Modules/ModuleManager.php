<?php

namespace App\Central\Modules;

use App\Central\Entitlement\SubscriptionEntitlement;
use App\Central\Enums\ModuleSource;
use App\Central\Enums\TenantModuleStatus;
use App\Central\Models\Module;
use App\Central\Models\ProvisioningRun;
use App\Central\Models\Subscription;
use App\Central\Models\SubscriptionAddon;
use App\Central\Models\Tenant;
use App\Central\Models\TenantModule;
use App\Central\Provisioning\ProvisioningRunner;
use App\Support\Modules\ModuleRegistry;
use App\Support\Modules\ModuleState;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Module lifecycle per tenant (TDD §6 "ModuleManager: langkah aktivasi").
 *
 * activate():  entitlement check → dependencies (activated too when the
 *              tenant is entitled to them, otherwise refused with a clear
 *              message) → tenant_modules `installing` → install_module run
 *              on the provisioning queue (migrate, permissions, seed).
 * deactivate(): refused for core modules and while an active module
 *              requires this one. Data stays in the tenant DB.
 */
class ModuleManager
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly SubscriptionEntitlement $entitlement,
        private readonly ProvisioningRunner $runner,
    ) {}

    /**
     * @return ProvisioningRun|null the install run, or null when everything
     *                              was already active
     */
    public function activate(Tenant $tenant, string $code): ?ProvisioningRun
    {
        $manifest = $this->manifest($code);
        $entitlement = $this->entitlement->forTenant($tenant);
        $entitlement->flush();

        if (! $entitlement->canActivate($code)) {
            throw new ModuleActivationException("Modul {$manifest->name} tidak termasuk paket atau add-on langganan ini.");
        }

        $needed = [...$this->registry->dependenciesOf($code), $code];
        $toInstall = array_values(array_filter($needed, fn ($c) => $entitlement->state($c) !== ModuleState::Active));

        if ($toInstall === []) {
            return null;
        }

        $missing = array_filter($toInstall, fn ($c) => ! $entitlement->canActivate($c));
        if ($missing) {
            $names = implode(', ', array_map(fn ($c) => $this->registry->get($c)->name, $missing));

            throw new ModuleActivationException("Modul {$manifest->name} membutuhkan {$names}, yang tidak termasuk paket atau add-on langganan ini. Tambahkan sebagai add-on terlebih dahulu.");
        }

        $rows = $this->rows($tenant);
        $busy = array_filter($toInstall, fn ($c) => $rows->get($c)?->status === TenantModuleStatus::Installing);
        if ($busy) {
            throw new ModuleActivationException('Modul '.implode(', ', $busy).' sedang diinstal. Tunggu sampai selesai atau retry run yang gagal.');
        }

        $run = DB::connection('central')->transaction(function () use ($tenant, $toInstall, $rows) {
            $source = $this->sources($tenant);
            $ids = Module::whereIn('code', $toInstall)->pluck('id', 'code');

            foreach ($toInstall as $c) {
                TenantModule::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'module_id' => $ids[$c]],
                    ['status' => TenantModuleStatus::Installing, 'source' => $rows->get($c)?->source ?? $source($c), 'last_error' => null],
                );
            }

            return $this->runner->createInstallRun($tenant, $toInstall);
        });

        $this->runner->dispatch($run);

        return $run;
    }

    public function deactivate(Tenant $tenant, string $code): void
    {
        $manifest = $this->manifest($code);

        if ($manifest->isCore) {
            throw new ModuleActivationException("Modul inti {$manifest->name} tidak bisa dinonaktifkan.");
        }

        $rows = $this->rows($tenant);
        $active = $rows->filter(fn ($row) => in_array($row->status, [TenantModuleStatus::Active, TenantModuleStatus::Readonly], true))->keys()->all();
        $blockers = array_intersect($this->registry->dependentsOf($code), $active);

        if ($blockers) {
            $names = implode(', ', array_map(fn ($c) => $this->registry->get($c)->name, $blockers));

            throw new ModuleActivationException("Modul {$manifest->name} masih dibutuhkan oleh {$names}. Nonaktifkan modul tersebut terlebih dahulu.");
        }

        $rows->get($code)?->update(['status' => TenantModuleStatus::Inactive]);
    }

    /**
     * Operator shortcut until billing (Sprint 5): adds $code as an add-on to
     * the current subscription, together with the add-on modules it requires
     * that the plan does not include, then activates it.
     *
     * @return list<string> module codes added as add-ons
     */
    public function addAddon(Tenant $tenant, string $code): array
    {
        $this->manifest($code);
        $subscription = Subscription::where('tenant_id', $tenant->id)->latest('id')->first()
            ?? throw new ModuleActivationException('Tenant ini belum punya langganan.');
        $entitlement = $this->entitlement->forTenant($tenant);
        $entitlement->flush();

        $added = [];

        foreach ([...$this->registry->dependenciesOf($code), $code] as $c) {
            if ($entitlement->canActivate($c)) {
                continue;
            }

            $manifest = $this->registry->get($c);
            if (! $manifest->isAddon) {
                throw new ModuleActivationException("Modul {$manifest->name} tidak dijual sebagai add-on; ganti ke paket yang memuatnya.");
            }

            $module = Module::where('code', $c)->firstOrFail();
            SubscriptionAddon::create([
                'subscription_id' => $subscription->id,
                'module_id' => $module->id,
                'price' => $module->price_monthly,
                'started_at' => now(),
            ]);
            $added[] = $c;
        }

        $this->activate($tenant, $code);

        return $added;
    }

    private function manifest(string $code)
    {
        if (! $this->registry->has($code)) {
            throw new ModuleActivationException("Modul [{$code}] tidak dikenal.");
        }

        return $this->registry->get($code);
    }

    /** @return Collection<string, TenantModule> keyed by module code */
    private function rows(Tenant $tenant)
    {
        return TenantModule::with('module')->where('tenant_id', $tenant->id)->get()->keyBy('module.code');
    }

    /** @return \Closure(string): ModuleSource */
    private function sources(Tenant $tenant): \Closure
    {
        $plan = Subscription::with('plan.modules')->where('tenant_id', $tenant->id)->latest('id')->first()
            ?->plan->modules->pluck('code')->all() ?? [];

        return fn (string $code) => match (true) {
            $this->registry->get($code)->isCore => ModuleSource::Core,
            in_array($code, $plan, true) => ModuleSource::Plan,
            default => ModuleSource::Addon,
        };
    }
}
