<?php

namespace Database\Seeders;

use App\Central\Models\Module;
use App\Central\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Example plans from TDD §8. Prices and limits are PLACEHOLDERS until the
 * business decides them (TDD open question).
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            'STARTER' => [
                'attributes' => ['name' => 'Starter', 'price_monthly' => 299000, 'price_yearly' => 2990000,
                    'max_users' => 5, 'max_companies' => 1, 'max_stores' => 2, 'sort' => 10],
                'modules' => ['core', 'master', 'inventory', 'pos', 'reporting'],
            ],
            'BUSINESS' => [
                'attributes' => ['name' => 'Business', 'price_monthly' => 799000, 'price_yearly' => 7990000,
                    'max_users' => 25, 'max_companies' => 1, 'max_stores' => 10, 'sort' => 20],
                'modules' => ['core', 'master', 'inventory', 'pos', 'reporting', 'workflow', 'procurement', 'finance'],
            ],
            'ENTERPRISE' => [
                'attributes' => ['name' => 'Enterprise', 'price_monthly' => 0, 'price_yearly' => 0,
                    'max_users' => null, 'max_companies' => null, 'max_stores' => null, 'is_public' => false, 'sort' => 30],
                'modules' => '*',
            ],
        ];

        foreach ($plans as $code => $def) {
            $plan = Plan::updateOrCreate(['code' => $code], $def['attributes'] + ['trial_days' => 14]);

            $moduleIds = $def['modules'] === '*'
                ? Module::where('status', '!=', 'deprecated')->pluck('id')
                : Module::whereIn('code', $def['modules'])->pluck('id');

            $plan->modules()->sync($moduleIds);
        }
    }
}
