<?php

namespace App\Providers;

use App\Central\Console\CreateTenantCommand;
use App\Central\Console\DeleteTenantCommand;
use App\Support\Modules\Console\SyncModulesCommand;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class, fn () => new ModuleRegistry(config('erp.modules_path')));
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncModulesCommand::class,
                CreateTenantCommand::class,
                DeleteTenantCommand::class,
            ]);
        }
    }
}
