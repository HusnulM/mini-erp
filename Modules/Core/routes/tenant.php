<?php

use Illuminate\Support\Facades\Route;

/*
| Core tenant routes. Loaded by CoreServiceProvider inside the
| ['web', 'tenant'] middleware group, names prefixed with "core.".
*/

Route::get('/', fn () => view('core::dashboard', ['tenant' => tenant()]))->name('dashboard');
