<?php

use App\Support\CentralRoutes;
use App\Tenancy\Middleware\EnsureModuleIsActive;
use App\Tenancy\Middleware\EnsureTenantIsActive;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Modules\Core\Http\Middleware\EnsureSetupCompleted;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',      // central domains only
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Applied to every module's routes/tenant.php (together with 'web').
        $middleware->alias([
            'module' => EnsureModuleIsActive::class,
            'setup' => EnsureSetupCompleted::class,
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
        ]);

        // TDD §3 order: tenancy → tenant active → auth → setup → module → permission.
        // `auth` is in Laravel's priority list, so the rest must be too, or
        // a suspended tenant would get the login page instead of its notice.
        $middleware->prependToPriorityList(AuthenticatesRequests::class, EnsureTenantIsActive::class);
        $middleware->appendToPriorityList(AuthenticatesRequests::class, EnsureSetupCompleted::class);
        $middleware->appendToPriorityList(EnsureSetupCompleted::class, EnsureModuleIsActive::class);
        $middleware->appendToPriorityList(EnsureModuleIsActive::class, PermissionMiddleware::class);

        $middleware->group('tenant', [
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
            EnsureTenantIsActive::class,
        ]);

        // Operators log in on the central panel, tenant users on their subdomain.
        $middleware->redirectGuestsTo(fn (Request $request) => CentralRoutes::isCentralHost($request->getHost())
            ? central_route('admin.login')
            : '/login');
        $middleware->redirectUsersTo(fn (Request $request) => CentralRoutes::isCentralHost($request->getHost())
            ? central_route('admin.tenants.index')
            : '/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
