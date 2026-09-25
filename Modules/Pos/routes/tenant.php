<?php

use Illuminate\Support\Facades\Route;

/*
| Pos tenant routes. Loaded by ModuleServiceProvider inside
| ['web', 'tenant', 'module:pos'], names prefixed with "pos.".
| Placeholder page until the module is built (Phase 1).
*/

Route::middleware(['auth', 'permission:pos.transaction.view'])->group(function () {
    Route::get('pos', fn () => view('core::module-home', ['module' => 'pos']))->name('index');
});
