<?php

namespace Database\Seeders;

use App\Central\Enums\CentralUserRole;
use App\Central\Models\CentralUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * First SaaS operator account. Password comes from CENTRAL_ADMIN_PASSWORD,
 * or a random one is generated and printed once.
 */
class CentralUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('CENTRAL_ADMIN_EMAIL', 'admin@erp.localhost');

        if (CentralUser::where('email', $email)->exists()) {
            return;
        }

        $password = env('CENTRAL_ADMIN_PASSWORD') ?: Str::password(16, symbols: false);

        CentralUser::create([
            'name' => 'Owner',
            'email' => $email,
            'password' => $password,
            'role' => CentralUserRole::Owner,
        ]);

        $this->command?->info("Central admin: {$email} / ".(env('CENTRAL_ADMIN_PASSWORD') ? '(from CENTRAL_ADMIN_PASSWORD)' : $password));
    }
}
