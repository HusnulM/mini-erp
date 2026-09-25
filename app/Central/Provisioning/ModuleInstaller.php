<?php

namespace App\Central\Provisioning;

use App\Central\Enums\TenantModuleStatus;
use App\Central\Models\Tenant;
use App\Central\Models\TenantModule;
use App\Support\Modules\ModuleRegistry;
use Modules\Core\Models\InstalledModule;
use Throwable;

/**
 * install_modules step: migrates every module the tenant is entitled to
 * (tenant_modules), in dependency order, and records it in the tenant's
 * installed_modules. A module already in installed_modules is not migrated
 * again, so the step can be retried.
 *
 * Sprint 3 moves this into ModuleManager (permissions, Installer::seed(),
 * activation of add-ons after provisioning).
 */
class ModuleInstaller
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly TenantDatabaseProvisioner $database,
    ) {}

    /** @return list<string> installed module codes, in install order */
    public function install(Tenant $tenant): array
    {
        $entitled = TenantModule::with('module')
            ->where('tenant_id', $tenant->id)
            ->where('status', '!=', TenantModuleStatus::Inactive)
            ->get()
            ->keyBy(fn (TenantModule $tm) => $tm->module->code);

        // Registry order = topological order (dependencies first).
        $codes = array_values(array_filter(
            array_keys($this->registry->all()),
            fn (string $code) => $entitled->has($code)
        ));

        $installed = $tenant->run(fn () => InstalledModule::pluck('version', 'module_code')->all());

        foreach ($codes as $code) {
            $manifest = $this->registry->get($code);
            $row = $entitled[$code];

            try {
                if (! isset($installed[$code])) {
                    $this->database->migrateModule($tenant, $code);
                    $tenant->run(fn () => InstalledModule::updateOrCreate(
                        ['module_code' => $code],
                        ['version' => $manifest->version, 'installed_at' => now(), 'last_migrated_at' => now()],
                    ));
                }
            } catch (Throwable $e) {
                $row->update(['status' => TenantModuleStatus::Failed, 'last_error' => $e->getMessage()]);

                throw $e;
            }

            $row->update([
                'status' => TenantModuleStatus::Active,
                'installed_version' => $installed[$code] ?? $manifest->version,
                'activated_at' => $row->activated_at ?? now(),
                'last_error' => null,
            ]);
        }

        return $codes;
    }
}
