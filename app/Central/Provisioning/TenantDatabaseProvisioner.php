<?php

namespace App\Central\Provisioning;

use App\Central\Models\Tenant;
use App\Support\Modules\ModuleRegistry;
use App\Tenancy\Database\TenantDatabaseManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Database-level provisioning building blocks. Every method is idempotent so
 * the ProvisionTenant job (Sprint 2) can run them as retryable steps:
 *
 *   reserveNames → createDatabase → createDatabaseUser → migrateCoreModules → seed
 */
class TenantDatabaseProvisioner
{
    public function __construct(private readonly ModuleRegistry $modules) {}

    /** Step 1: generate db name / user / password once and store them. */
    public function reserveNames(Tenant $tenant): void
    {
        $suffix = strtolower($tenant->getTenantKey());
        $config = config('erp.tenant_database');

        $tenant->db_name ??= $config['prefix'].$suffix;
        $tenant->db_username ??= $config['user_prefix'].$suffix;
        $tenant->db_password ??= Str::password(32, symbols: false);
        $tenant->save();
    }

    /** Step 2 */
    public function createDatabase(Tenant $tenant): void
    {
        $this->assertReserved($tenant);
        $this->manager($tenant)->ensureDatabase($tenant->db_name);
    }

    /** Step 3: MySQL account that can only touch this tenant's database. */
    public function createDatabaseUser(Tenant $tenant): void
    {
        $this->assertReserved($tenant);
        $this->manager($tenant)->createUser($tenant->database());
    }

    /** Step 4: migrations of core modules (core, master), in dependency order. */
    public function migrateCoreModules(Tenant $tenant): void
    {
        foreach ($this->modules->core() as $manifest) {
            $this->migrateModule($tenant, $manifest->code);
        }
    }

    /** Runs one module's tenant migrations (also used by ModuleManager, Sprint 3). */
    public function migrateModule(Tenant $tenant, string $code): void
    {
        $path = $this->modules->get($code)->tenantMigrationPath();

        if (! is_dir($path) || ! glob($path.'/*.php')) {
            return;
        }

        $exit = Artisan::call('tenants:migrate', [
            '--tenants' => [$tenant->getTenantKey()],
            '--path' => [$path],
            '--realpath' => true,
            '--force' => true,
        ]);

        if ($exit !== 0) {
            throw new RuntimeException("Migrating module [{$code}] for tenant [{$tenant->getTenantKey()}] failed:\n".Artisan::output());
        }
    }

    /** Step 5 */
    public function seed(Tenant $tenant): void
    {
        $exit = Artisan::call('tenants:seed', [
            '--tenants' => [$tenant->getTenantKey()],
            '--force' => true,
        ]);

        if ($exit !== 0) {
            throw new RuntimeException("Seeding tenant [{$tenant->getTenantKey()}] failed:\n".Artisan::output());
        }
    }

    /** Drops the database and the MySQL account. Operator/dev use only. */
    public function destroy(Tenant $tenant): void
    {
        $manager = $this->manager($tenant);

        if ($tenant->db_name) {
            $manager->dropDatabase($tenant->db_name);
        }

        $manager->deleteUser($tenant->database());
    }

    public function databaseExists(Tenant $tenant): bool
    {
        return $tenant->db_name !== null && $this->manager($tenant)->databaseExists($tenant->db_name);
    }

    private function manager(Tenant $tenant): TenantDatabaseManager
    {
        $manager = $tenant->database()->manager();

        if (! $manager instanceof TenantDatabaseManager) {
            throw new RuntimeException('config/tenancy.php must map the mysql driver to '.TenantDatabaseManager::class.'.');
        }

        return $manager;
    }

    private function assertReserved(Tenant $tenant): void
    {
        if (! $tenant->db_name || ! $tenant->db_username || ! $tenant->db_password) {
            throw new RuntimeException("Tenant [{$tenant->getTenantKey()}] has no reserved database names; run reserveNames() first.");
        }
    }
}
