<?php

use Illuminate\Support\Facades\Route;
use Modules\Procurement\Http\Controllers\ConfigurationController;

/*
| Procurement tenant routes. Loaded by ModuleServiceProvider inside
| ['web', 'tenant', 'setup', 'module:procurement'], names prefixed with
| "procurement.". Transactions (PR, PO, GR) come in Phase 1.
*/

Route::middleware(['auth', 'permission:procurement.purchase_order.view'])->group(function () {
    Route::get('procurement', fn () => view('core::module-home', ['module' => 'procurement']))->name('index');
});

// Configuration, also the module's setup-wizard step (reachable during setup).
Route::middleware(['auth', 'permission:procurement.settings.manage'])
    ->withoutMiddleware('setup')
    ->prefix('procurement/configuration')
    ->controller(ConfigurationController::class)
    ->group(function () {
        Route::get('/', 'index')->name('setup');
        Route::post('types', 'storeType')->name('types.store');
        Route::get('types/{type}/edit', 'editType')->name('types.edit');
        Route::put('types/{type}', 'updateType')->name('types.update');
        Route::post('groups', 'storeGroup')->name('groups.store');
        Route::get('groups/{group}/edit', 'editGroup')->name('groups.edit');
        Route::put('groups/{group}', 'updateGroup')->name('groups.update');
    });
