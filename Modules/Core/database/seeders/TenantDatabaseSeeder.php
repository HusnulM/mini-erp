<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\Currency;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Root seeder for a new tenant database (`tenancy.seeder_parameters`),
 * run by the seed_defaults provisioning step. Must stay idempotent.
 *
 * Seeds what every tenant needs before its first login: the SUPER ADMIN
 * role (given to the owner by create_admin) and currencies. Company-level
 * defaults (tax codes, fiscal year, document sequences) are created by the
 * setup wizard, because they need a company.
 */
class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(['name' => config('erp.provisioning.admin_role'), 'guard_name' => 'web']);

        foreach ([
            ['code' => 'IDR', 'name' => 'Rupiah', 'symbol' => 'Rp', 'decimals' => 0],
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimals' => 2],
            ['code' => 'SGD', 'name' => 'Singapore Dollar', 'symbol' => 'S$', 'decimals' => 2],
        ] as $currency) {
            Currency::firstOrCreate(['code' => $currency['code']], $currency);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
