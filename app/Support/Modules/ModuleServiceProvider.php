<?php

namespace App\Support\Modules;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Base provider for every module in Modules/.
 *
 * Deliberately does NOT call loadMigrationsFrom(): module migrations belong
 * to tenant databases and are run by the provisioner / ModuleManager, never
 * by `php artisan migrate` on the central database.
 *
 * All modules are loaded in code for every request; whether a tenant may use
 * a module is decided per request by ModuleEntitlement (Sprint 3 adds the
 * `module:{code}` middleware to the route group below).
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /** Module code, must equal "code" in module.json. */
    protected string $code;

    public function register(): void
    {
        $config = $this->modulePath('config/config.php');

        if (is_file($config)) {
            $this->mergeConfigFrom($config, "module.{$this->code}");
        }
    }

    public function boot(): void
    {
        $this->loadViewsFrom($this->modulePath('resources/views'), $this->code);
        $this->loadTranslationsFrom($this->modulePath('lang'), $this->code);
        $this->bootTenantRoutes();
    }

    protected function bootTenantRoutes(): void
    {
        $routes = $this->modulePath('routes/tenant.php');

        if (! is_file($routes) || $this->app->routesAreCached()) {
            return;
        }

        Route::middleware(['web', 'tenant'])
            ->name($this->code.'.')
            ->group($routes);
    }

    protected function modulePath(string $path = ''): string
    {
        $base = dirname((new \ReflectionClass(static::class))->getFileName(), 3);

        return $path === '' ? $base : $base.'/'.$path;
    }
}
