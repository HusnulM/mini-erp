<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Seeds the CENTRAL database. Tenant databases are seeded by
 * Modules\Core\Database\Seeders\TenantDatabaseSeeder.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('modules:sync');

        $this->call([
            PlanSeeder::class,
            CentralUserSeeder::class,
        ]);
    }
}
