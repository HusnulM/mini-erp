<?php

use Illuminate\Support\Facades\Route;

/*
| Finance tenant routes. Loaded by ModuleServiceProvider inside
| ['web', 'tenant', 'module:finance'], names prefixed with "finance.".
| Placeholder page until the module is built (Phase 1).
*/

Route::middleware(['auth', 'permission:finance.journal.view'])->group(function () {
    Route::get('finance', fn () => view('core::module-home', ['module' => 'finance']))->name('index');
});
