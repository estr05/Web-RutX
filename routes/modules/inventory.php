<?php

use App\Http\Controllers\Pages\InventoryController;
use Illuminate\Support\Facades\Route;

/**
 * Rutas del módulo Inventario.
 * Día 2 — scaffold; sin endpoint v2.
 * Sprint 3 — protegidas por auth.session.
 * Sprint 4 — piloto por ruta vía InventoryController (una línea) y
 * permiso inventory.read (EnsurePermission, segunda barrera local).
 */
Route::middleware(['auth.session', 'permission:inventory.read'])
    ->prefix('inventory')
    ->name('inventory.')
    ->group(function () {
        Route::get('/routes', [InventoryController::class, 'routes'])->name('routes');
        Route::get('/rejected', fn () => view('modules.inventory.rejected'))->name('rejected');
        Route::get('/shrinkage', fn () => view('modules.inventory.shrinkage'))->name('shrinkage');
    });
