<?php

namespace App\Support\Modules\Console;

use App\Central\Models\Module;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Copies Modules/{Name}/module.json into the central `modules` and
 * `module_dependencies` tables. Run on every deploy.
 */
class SyncModulesCommand extends Command
{
    protected $signature = 'modules:sync';

    protected $description = 'Sync module manifests (module.json) into the central module catalog';

    public function handle(ModuleRegistry $registry): int
    {
        $manifests = $registry->all();

        DB::connection('central')->transaction(function () use ($manifests) {
            $ids = [];
            $sort = 0;

            foreach ($manifests as $code => $m) {
                $ids[$code] = Module::updateOrCreate(['code' => $code], [
                    'name' => $m->name,
                    'description' => $m->description,
                    'is_core' => $m->isCore,
                    'is_addon' => $m->isAddon,
                    'price_monthly' => $m->priceMonthly,
                    'version' => $m->version,
                    'status' => $m->status,
                    'manifest' => $m->raw,
                    'sort' => $sort += 10,
                ])->id;
            }

            $deps = DB::connection('central')->table('module_dependencies');
            $deps->whereIn('module_id', array_values($ids))->delete();

            foreach ($manifests as $code => $m) {
                foreach ($m->requires as $dep) {
                    $deps->insert(['module_id' => $ids[$code], 'requires_module_id' => $ids[$dep], 'is_optional' => false]);
                }
                foreach ($m->optional as $dep) {
                    $deps->insert(['module_id' => $ids[$code], 'requires_module_id' => $ids[$dep], 'is_optional' => true]);
                }
            }

            // Modules removed from the codebase are never deleted (tenants may
            // still hold their data); they are marked deprecated instead.
            Module::whereNotIn('code', array_keys($manifests))->update(['status' => 'deprecated']);
        });

        $this->table(
            ['Code', 'Name', 'Version', 'Core', 'Requires', 'Optional'],
            array_map(fn ($m) => [
                $m->code, $m->name, $m->version, $m->isCore ? 'yes' : '',
                implode(', ', $m->requires), implode(', ', $m->optional),
            ], array_values($manifests))
        );

        $this->components->info(count($manifests).' modules synced.');

        return self::SUCCESS;
    }
}
