<?php

use Illuminate\Support\Facades\Route;

/*
| Reporting tenant routes. Loaded by ModuleServiceProvider inside
| ['web', 'tenant', 'module:reporting'], names prefixed with "reporting.".
| Placeholder page until the module is built (Phase 1).
*/

Route::middleware(['auth', 'permission:reporting.dashboard.read'])->group(function () {
    Route::get('reporting', fn () => view('core::module-home', ['module' => 'reporting']))->name('index');
});
