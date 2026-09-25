<?php

use App\Central\Http\Controllers\Admin\LoginController;
use App\Central\Http\Controllers\Admin\ProvisioningController;
use App\Central\Http\Controllers\Admin\TenantController;
use App\Central\Http\Controllers\RegistrationController;
use Illuminate\Support\Facades\Route;

/*
| Included once per central domain by routes/web.php.
*/

Route::get('/', fn () => view('central.home'))->name('home');

// Self-service registration (TDD §7)
Route::get('register', [RegistrationController::class, 'create'])->name('register');
Route::post('register', [RegistrationController::class, 'store'])->middleware('throttle:registration')->name('register.store');
Route::get('register/sent', [RegistrationController::class, 'sent'])->name('register.sent');
Route::get('register/verify/{tenant}/{hash}', [RegistrationController::class, 'verify'])
    ->middleware(['signed', 'throttle:10,1'])
    ->name('register.verify');

// Operator panel (guard "central")
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:central')->group(function () {
        Route::get('login', [LoginController::class, 'create'])->name('login');
        Route::post('login', [LoginController::class, 'store'])->name('login.store');
    });

    Route::middleware('auth:central')->group(function () {
        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
        Route::get('/', [TenantController::class, 'index'])->name('tenants.index');
        Route::get('tenants/{tenant}', [TenantController::class, 'show'])->name('tenants.show');
        Route::post('tenants/{tenant}/runs/{run}/steps/{step}/retry', [ProvisioningController::class, 'retry'])
            ->middleware('can:retry-provisioning')
            ->name('provisioning.retry');
    });
});
