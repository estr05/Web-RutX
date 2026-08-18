<?php

use App\Http\Controllers\Pages\CustomersController;
use Illuminate\Support\Facades\Route;

/**
 * Rutas del módulo Clientes.
 * Día 2 — scaffold; sin endpoint v2.
 * Sprint 3 — protegidas por auth.session.
 * Sprint 4 — catálogo funcional vía CustomersController (una línea) y
 * permiso customers.read (EnsurePermission, segunda barrera local).
 */
Route::middleware(['auth.session', 'permission:customers.read'])
    ->prefix('customers')
    ->name('customers.')
    ->group(function () {
        Route::get('/', [CustomersController::class, 'index'])->name('index');
        Route::get('/transfer', fn () => view('modules.customers.transfer'))->name('transfer');
    });
