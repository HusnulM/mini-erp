<?php

namespace App\Tenancy\Database;

use Stancl\Tenancy\DatabaseConfig;

/**
 * Builds the tenant connection from real columns on `tenants`
 * (db_name, db_username, db_password, db_host) instead of stancl's
 * "tenancy_db_*" JSON keys.
 *
 * The template connection is `provisioner` (see config/tenancy.php), so the
 * username/password returned here MUST override it — otherwise tenant
 * queries would run with provisioner privileges.
 */
class TenantDatabaseConfig extends DatabaseConfig
{
    public function tenantConfig(): array
    {
        $tenant = $this->tenant;

        $config = [
            'username' => $tenant->getInternal('db_username'),
            'password' => $tenant->getInternal('db_password'),
        ];

        if ($host = $tenant->getInternal('db_host')) {
            $config['host'] = $host;
        }

        return $config;
    }

    public function connection(): array
    {
        $config = parent::connection();

        if (empty($config['username']) || empty($config['password'])) {
            throw new \RuntimeException(
                "Tenant [{$this->tenant->getTenantKey()}] has no database credentials; refusing to fall back to the provisioner account."
            );
        }

        return $config;
    }
}
