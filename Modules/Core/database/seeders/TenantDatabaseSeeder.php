<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Root seeder for a new tenant database (`tenancy.seeder_parameters`),
 * run by the seed_defaults provisioning step. Must stay idempotent.
 *
 * Sprint 2 seeds the SUPER ADMIN role (granted to the first user by the
 * create_admin step). Sprint 4 adds the other default roles (PRD §7),
 * currency IDR, tax codes, lookups and document sequences, together with
 * their tables.
 */
class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(['name' => config('erp.provisioning.admin_role'), 'guard_name' => 'web']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
