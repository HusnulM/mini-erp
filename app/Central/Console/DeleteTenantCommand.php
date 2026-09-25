<?php

namespace App\Central\Console;

use App\Central\Models\Tenant;
use App\Central\Provisioning\TenantDatabaseProvisioner;
use Illuminate\Console\Command;

/**
 * Development helper: permanently drops a tenant, its database and MySQL
 * user. Refuses to run in production (TDD §8: cancelled tenants are archived,
 * never dropped by a command).
 */
class DeleteTenantCommand extends Command
{
    protected $signature = 'erp:tenant:delete {slug} {--force : Skip confirmation}';

    protected $description = '[dev] Drop a tenant with its database and MySQL user';

    public function handle(TenantDatabaseProvisioner $provisioner): int
    {
        if ($this->laravel->isProduction()) {
            $this->components->error('Not available in production.');

            return self::FAILURE;
        }

        $tenant = Tenant::where('slug', $this->argument('slug'))->first();

        if (! $tenant) {
            $this->components->error('Tenant not found.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Drop tenant {$tenant->slug} and database {$tenant->db_name}?")) {
            return self::FAILURE;
        }

        $provisioner->destroy($tenant);
        $tenant->delete();

        $this->components->info("Tenant {$tenant->slug} deleted.");

        return self::SUCCESS;
    }
}
