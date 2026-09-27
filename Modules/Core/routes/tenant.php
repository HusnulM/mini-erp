<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\AuditLogController;
use Modules\Core\Http\Controllers\LoginController;
use Modules\Core\Http\Controllers\OrganizationController;
use Modules\Core\Http\Controllers\PasswordController;
use Modules\Core\Http\Controllers\RoleController;
use Modules\Core\Http\Controllers\SettingsController;
use Modules\Core\Http\Controllers\SetupController;
use Modules\Core\Http\Controllers\UserController;

/*
| Core tenant routes. Loaded by CoreServiceProvider inside
| ['web', 'tenant', 'setup', 'module:core'], names prefixed with "core.".
*/

// Signing in and out must work whatever the setup and module state
// (expired subscription: users can still log in to view and export data).
Route::withoutMiddleware(['setup', 'module:core'])->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [LoginController::class, 'create'])->name('login');
        Route::post('login', [LoginController::class, 'store'])->name('login.store');
        Route::get('password/forgot', [PasswordController::class, 'request'])->name('password.request');
        Route::post('password/forgot', [PasswordController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
        Route::get('password/reset/{token}', [PasswordController::class, 'edit'])->name('password.reset');
        Route::post('password/reset', [PasswordController::class, 'update'])->name('password.update');
    });

    Route::post('logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');
});

Route::middleware('auth')->group(function () {
    Route::get('/', fn () => view('core::dashboard', ['tenant' => tenant()]))->name('dashboard');

    // Setup wizard (TDD §9), and the pages its steps use: reachable while
    // setup is not finished.
    Route::withoutMiddleware('setup')->group(function () {
        Route::middleware('permission:core.settings.manage')->prefix('setup')->name('setup.')->controller(SetupController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('company', 'company')->name('company');
            Route::post('company', 'saveCompany')->name('company.save');
            Route::get('fiscal-year', 'fiscalYear')->name('fiscal-year');
            Route::post('fiscal-year', 'saveFiscalYear')->name('fiscal-year.save');
            Route::get('organization', 'organization')->name('organization');
            Route::post('organization', 'saveOrganization')->name('organization.save');
            Route::get('tax', 'tax')->name('tax');
            Route::post('tax', 'saveTax')->name('tax.save');
            Route::get('coa', 'coa')->name('coa');
            Route::post('coa', 'saveCoa')->name('coa.save');
            Route::get('numbering', 'numbering')->name('numbering');
            Route::post('numbering', 'saveNumbering')->name('numbering.save');
            Route::get('users', 'users')->name('users');
            Route::post('steps/{step}/done', 'markDone')->name('done');
            Route::post('steps/{step}/skip', 'skip')->name('skip');
            Route::post('finish', 'finish')->name('finish');
        });

        Route::prefix('organization')->name('organization.')->controller(OrganizationController::class)->group(function () {
            Route::get('/', 'index')->middleware('permission:core.company.view')->name('index');
            Route::post('companies', 'storeCompany')->middleware('permission:core.company.create')->name('companies.store');
            Route::post('branches', 'storeBranch')->middleware('permission:core.branch.create')->name('branches.store');
            Route::post('stores', 'storeStore')->middleware('permission:core.store.create')->name('stores.store');
            Route::post('warehouses', 'storeWarehouse')->middleware('permission:core.warehouse.create')->name('warehouses.store');
        });

        Route::prefix('users')->name('users.')->controller(UserController::class)->group(function () {
            Route::get('/', 'index')->middleware('permission:core.user.view')->name('index');
            Route::get('create', 'create')->middleware('permission:core.user.create')->name('create');
            Route::post('/', 'store')->middleware('permission:core.user.create')->name('store');
            Route::get('{user}/edit', 'edit')->middleware('permission:core.user.update')->name('edit');
            Route::put('{user}', 'update')->middleware('permission:core.user.update')->name('update');
        });

        Route::prefix('roles')->name('roles.')->controller(RoleController::class)->group(function () {
            Route::get('/', 'index')->middleware('permission:core.role.view')->name('index');
            Route::get('create', 'create')->middleware('permission:core.role.create')->name('create');
            Route::post('/', 'store')->middleware('permission:core.role.create')->name('store');
            Route::get('{role}/edit', 'edit')->middleware('permission:core.role.update')->name('edit');
            Route::put('{role}', 'update')->middleware('permission:core.role.update')->name('update');
        });

        // Permission {module}.settings.manage is checked in the controller.
        Route::get('settings/{module}', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings/{module}', [SettingsController::class, 'update'])->name('settings.update');
    });

    Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('permission:core.audit_log.read')->name('audit-logs.index');
});
