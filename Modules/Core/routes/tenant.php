<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\LoginController;

/*
| Core tenant routes. Loaded by CoreServiceProvider inside the
| ['web', 'tenant'] middleware group, names prefixed with "core.".
*/

Route::get('/', fn () => view('core::dashboard', ['tenant' => tenant()]))->name('dashboard');

// Signing in and out must work even when Core is readonly (expired
// subscription: users can still log in to view and export data).
Route::withoutMiddleware('module:core')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [LoginController::class, 'create'])->name('login');
        Route::post('login', [LoginController::class, 'store'])->name('login.store');
    });

    Route::post('logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');
});
