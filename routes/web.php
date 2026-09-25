<?php

use App\Support\CentralRoutes;
use Illuminate\Support\Facades\Route;

/*
| Central routes: landing, registration, operator panel (routes/central.php).
| Bound to central domains so they never answer on a tenant host. The first
| central domain gets "central.*" names, the others "central.{domain}.*";
| use central_route('name') to link to them.
| Tenant routes live in Modules/{Name}/routes/tenant.php.
*/

foreach (CentralRoutes::domains() as $domain) {
    Route::domain($domain)
        ->name(CentralRoutes::prefix($domain))
        ->group(base_path('routes/central.php'));
}
