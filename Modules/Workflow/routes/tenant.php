<?php

use Illuminate\Support\Facades\Route;

/*
| Workflow tenant routes. Loaded by ModuleServiceProvider inside
| ['web', 'tenant', 'module:workflow'], names prefixed with "workflow.".
| Placeholder page until the module is built (Phase 1).
*/

Route::middleware(['auth', 'permission:workflow.workflow.view'])->group(function () {
    Route::get('workflow', fn () => view('core::module-home', ['module' => 'workflow']))->name('index');
});
