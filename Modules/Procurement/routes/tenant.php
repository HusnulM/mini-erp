<?php

use Illuminate\Support\Facades\Route;

/*
| Procurement tenant routes. Loaded by ModuleServiceProvider inside
| ['web', 'tenant', 'module:procurement'], names prefixed with "procurement.".
| Placeholder page until the module is built (Phase 1).
*/

Route::middleware(['auth', 'permission:procurement.purchase_order.view'])->group(function () {
    Route::get('procurement', fn () => view('core::module-home', ['module' => 'procurement']))->name('index');
});
