<?php

namespace App\Central\Provisioning;

use App\Central\Enums\TenantModuleStatus;
use App\Central\Models\Module;
use App\Central\Models\Tenant;
use App\Central\Models\TenantModule;
use App\Support\Modules\Installer;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleRegistry;
use Modules\Core\Models\Company;
use Modules\Core\Models\InstalledModule;
use Modules\Core\Services\DocumentNumbers;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Installs a module into a tenant database (TDD §6 "ModuleManager: langkah
 * aktivasi", job part): tenant migrations → permissions (granted to SUPER
 * ADMIN) → document sequences per company → Installer::seed() →
 * installed_modules → tenant_modules active.
 *
 * Every part is idempotent: migrations are skipped when the module is
 * already in installed_modules, permissions and roles use findOrCreate, and
 * installers must seed with firstOrCreate. So a failed install can simply
 * be run again.
 */
class ModuleInstaller
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly TenantDatabaseProvisioner $database,
    ) {}

    /**
     * install_modules provisioning step: every module in tenant_modules
     * (except inactive ones), dependencies first.
     *
     * @return list<string> installed module codes, in install order
     */
    public function install(Tenant $tenant): array
    {
        $entitled = TenantModule::with('module')
            ->where('tenant_id', $tenant->id)
            ->where('status', '!=', TenantModuleStatus::Inactive)
            ->get()
            ->pluck('module.code')
            ->all();

        // Registry order = topological order (dependencies first).
        $codes = array_values(array_intersect(array_keys($this->registry->all()), $entitled));

        foreach ($codes as $code) {
            $this->installModule($tenant, $code);
        }

        return $codes;
    }

    /** @return string installed version */
    public function installModule(Tenant $tenant, string $code): string
    {
        $manifest = $this->registry->get($code);
        $row = TenantModule::where('tenant_id', $tenant->id)
            ->where('module_id', Module::where('code', $code)->value('id'))
            ->first() ?? throw new RuntimeException("Tenant has no tenant_modules row for [{$code}].");

        try {
            $installed = $tenant->run(fn () => InstalledModule::where('module_code', $code)->first());

            if (! $installed) {
                $this->database->migrateModule($tenant, $code);
            }

            $version = $tenant->run(function () use ($manifest, $installed) {
                $this->syncPermissions($manifest);

                // Document sequences of the module for every existing company;
                // companies created later get them from Organization::createCompany().
                foreach (Company::all() as $company) {
                    app(DocumentNumbers::class)->ensureForCompany($company, [$manifest->code]);
                }

                if ($manifest->installer) {
                    $installer = app($manifest->installer);

                    if (! $installer instanceof Installer) {
                        throw new RuntimeException("[{$manifest->installer}] must implement ".Installer::class.'.');
                    }

                    $installer->seed();
                }

                return ($installed ?? InstalledModule::create([
                    'module_code' => $manifest->code,
                    'version' => $manifest->version,
                    'installed_at' => now(),
                    'last_migrated_at' => now(),
                ]))->version;
            });
        } catch (Throwable $e) {
            $row->update(['status' => TenantModuleStatus::Failed, 'last_error' => $e->getMessage()]);

            throw $e;
        }

        $row->update([
            'status' => TenantModuleStatus::Active,
            'installed_version' => $version,
            'activated_at' => $row->activated_at ?? now(),
            'last_error' => null,
        ]);

        return $version;
    }

    /** Creates the module's permissions and gives them all to SUPER ADMIN. Runs in tenant context. */
    private function syncPermissions(ModuleManifest $manifest): void
    {
        $names = $manifest->expandedPermissions(config('erp.permission_actions'));

        foreach ($names as $name) {
            Permission::findOrCreate($name, 'web');
        }

        Role::findOrCreate(config('erp.provisioning.admin_role'), 'web')->givePermissionTo($names);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
