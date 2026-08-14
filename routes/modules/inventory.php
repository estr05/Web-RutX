<?php

use Illuminate\Support\Facades\Route;

/**
 * Rutas scaffold del módulo Inventario.
 * Día 2 — sin endpoint v2; solo estructura navegable.
 * Sprint 3 — protegidas por auth.session.
 */
Route::middleware('auth.session')->prefix('inventory')->name('inventory.')->group(function () {
    Route::get('/routes', fn () => view('modules.inventory.routes'))->name('routes');
    Route::get('/rejected', fn () => view('modules.inventory.rejected'))->name('rejected');
    Route::get('/shrinkage', fn () => view('modules.inventory.shrinkage'))->name('shrinkage');
});
