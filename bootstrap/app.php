<?php

use App\Support\CentralRoutes;
use App\Tenancy\Middleware\EnsureTenantIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
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
