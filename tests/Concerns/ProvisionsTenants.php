<?php

namespace Tests\Concerns;

use App\Central\Enums\ModuleSource;
use App\Central\Enums\TenantModuleStatus;
use App\Central\Enums\TenantStatus;
use App\Central\Models\Module;
use App\Central\Models\Tenant;
use App\Central\Models\TenantModule;
use App\Central\Provisioning\TenantDatabaseProvisioner;
use Illuminate\Support\Facades\DB;

/**
 * Creates real tenant databases on the test MySQL server and drops every
 * test tenant database/user afterwards (prefix erp_test_t_ / ut_).
 */
trait ProvisionsTenants
{
    protected function provisionTenant(string $slug, TenantStatus $status = TenantStatus::Trial): Tenant
    {
        $tenant = Tenant::create([
            'code' => strtoupper($slug),
            'name' => ucfirst($slug).' Store',
            'slug' => $slug,
            'owner_name' => 'Owner',
            'owner_email' => "owner@{$slug}.test",
            'status' => TenantStatus::Provisioning,
        ]);
        $tenant->domains()->create(['domain' => "{$slug}.erp.localhost"]);

        $p = app(TenantDatabaseProvisioner::class);
        $p->reserveNames($tenant);
        $p->createDatabase($tenant);
        $p->createDatabaseUser($tenant);
        $p->migrateCoreModules($tenant);
        $p->seed($tenant);

        // Core modules only, no subscription: what a tenant without a plan gets.
        foreach (Module::whereIn('code', config('erp.core_modules'))->get() as $module) {
            TenantModule::create([
                'tenant_id' => $tenant->id, 'module_id' => $module->id,
                'status' => TenantModuleStatus::Active, 'source' => ModuleSource::Core,
            ]);
        }

        $tenant->update(['status' => $status]);

        return $tenant->fresh();
    }

    protected function dropTestTenantDatabases(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $db = DB::connection('provisioner');

        foreach ($db->select("SELECT SCHEMA_NAME AS n FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME LIKE 'erp\\_test\\_t\\_%'") as $row) {
            $db->statement("DROP DATABASE IF EXISTS `{$row->n}`");
        }

        foreach ($db->select("SELECT user, host FROM mysql.user WHERE user LIKE 'ut\\_%'") as $row) {
            $db->statement("DROP USER IF EXISTS '{$row->user}'@'{$row->host}'");
        }
    }
}
