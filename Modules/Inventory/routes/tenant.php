<?php

use Illuminate\Support\Facades\Route;

/*
| Inventory tenant routes. Loaded by ModuleServiceProvider inside
| ['web', 'tenant', 'module:inventory'], names prefixed with "inventory.".
| Placeholder page until the module is built (Phase 1).
*/

Route::middleware(['auth', 'permission:inventory.stock.read'])->group(function () {
    Route::get('inventory', fn () => view('core::module-home', ['module' => 'inventory']))->name('index');
});
