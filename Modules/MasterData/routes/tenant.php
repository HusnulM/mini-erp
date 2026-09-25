<?php

use Illuminate\Support\Facades\Route;

/*
| MasterData tenant routes. Loaded by ModuleServiceProvider inside
| ['web', 'tenant', 'module:master'], names prefixed with "master.".
| Placeholder page until the module is built (Phase 1).
*/

Route::middleware(['auth', 'permission:master.product.view'])->group(function () {
    Route::get('master', fn () => view('core::module-home', ['module' => 'master']))->name('index');
});
