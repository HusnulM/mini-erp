<?php

use Illuminate\Support\Facades\Route;

/*
| Central routes: landing, registration, operator panel.
| Bound to central domains so they never answer on a tenant host.
| Tenant routes live in Modules/{Name}/routes/tenant.php.
*/

foreach (config('erp.central_domains') as $domain) {
    Route::domain($domain)->name(count(config('erp.central_domains')) > 1 ? "central.{$domain}." : 'central.')->group(function () {
        Route::get('/', fn () => view('central.home'))->name('home');
    });
}
