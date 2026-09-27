<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\PermissionRegistrar;
use Stancl\JobPipeline\JobPipeline;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Listeners;
use Stancl\Tenancy\Middleware;

class TenancyServiceProvider extends ServiceProvider
{
    // Tenant routes are registered by each module (routes/tenant.php),
    // see App\Support\Modules\ModuleServiceProvider.

    public function events()
    {
        return [
            // Tenant events
            Events\CreatingTenant::class => [],
            // Provisioning is NOT triggered by model events: the ProvisionTenant
            // job (Sprint 2) runs explicit, retryable steps instead.
            Events\TenantCreated::class => [],
            Events\SavingTenant::class => [],
            Events\TenantSaved::class => [],
            Events\UpdatingTenant::class => [],
            Events\TenantUpdated::class => [],
            Events\DeletingTenant::class => [],
            // Deleting a tenant row never drops its database automatically.
            Events\TenantDeleted::class => [],

            // Domain events
            Events\CreatingDomain::class => [],
            Events\DomainCreated::class => [],
            Events\SavingDomain::class => [],
            Events\DomainSaved::class => [],
            Events\UpdatingDomain::class => [],
            Events\DomainUpdated::class => [],
            Events\DeletingDomain::class => [],
            Events\DomainDeleted::class => [],

            // Database events
            Events\DatabaseCreated::class => [],
            Events\DatabaseMigrated::class => [],
            Events\DatabaseSeeded::class => [],
            Events\DatabaseRolledBack::class => [],
            Events\DatabaseDeleted::class => [],

            // Tenancy events
            Events\InitializingTenancy::class => [],
            Events\TenancyInitialized::class => [
                Listeners\BootstrapTenancy::class,
            ],

            Events\EndingTenancy::class => [],
            Events\TenancyEnded::class => [
                Listeners\RevertToCentralContext::class,
            ],

            Events\BootstrappingTenancy::class => [],
            Events\TenancyBootstrapped::class => [
                fn () => self::resetTenantAwareServices(),
            ],
            Events\RevertingToCentralContext::class => [],
            Events\RevertedToCentralContext::class => [
                fn () => self::resetTenantAwareServices(),
            ],

            // Resource syncing
            Events\SyncedResourceSaved::class => [
                Listeners\UpdateSyncedResource::class,
            ],

            // Fired only when a synced resource is changed in a different DB than the origin DB (to avoid infinite loops)
            Events\SyncedResourceChangedInForeignDatabase::class => [],
        ];
    }

    /**
     * Services that capture tenant state when first used, reset whenever
     * the tenant context changes (a worker handles several tenants):
     *  - spatie/laravel-permission keeps roles/permissions in memory and in
     *    the cache under one key: give every tenant its own key and drop
     *    what was loaded for the previous tenant;
     *  - the password broker holds the database connection of the tenant it
     *    was first built for.
     */
    public static function resetTenantAwareServices(): void
    {
        app()->forgetInstance('auth.password');
        Password::clearResolvedInstance('auth.password');

        $base = 'spatie.permission.cache';
        config(['permission.cache.key' => tenancy()->initialized ? $base.'.tenant.'.tenant()->getTenantKey() : $base]);

        app(PermissionRegistrar::class)->initializeCache();
    }

    public function register()
    {
        //
    }

    public function boot()
    {
        $this->bootEvents();

        // Unknown host on a tenant route: plain 404 instead of an exception page.
        Middleware\InitializeTenancyByDomain::$onFail = fn () => abort(404);

        $this->makeTenancyMiddlewareHighestPriority();
    }

    protected function bootEvents()
    {
        foreach ($this->events() as $event => $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof JobPipeline) {
                    $listener = $listener->toListener();
                }

                Event::listen($event, $listener);
            }
        }
    }

    protected function makeTenancyMiddlewareHighestPriority()
    {
        $tenancyMiddleware = [
            // Even higher priority than the initialization middleware
            Middleware\PreventAccessFromCentralDomains::class,

            Middleware\InitializeTenancyByDomain::class,
            Middleware\InitializeTenancyBySubdomain::class,
            Middleware\InitializeTenancyByDomainOrSubdomain::class,
            Middleware\InitializeTenancyByPath::class,
            Middleware\InitializeTenancyByRequestData::class,
        ];

        foreach (array_reverse($tenancyMiddleware) as $middleware) {
            $this->app[Kernel::class]->prependToMiddlewarePriority($middleware);
        }
    }
}
