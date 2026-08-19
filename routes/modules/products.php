<?php

use Illuminate\Support\Facades\Route;

/**
 * Rutas del módulo Productos.
 * Sprint 3 — protegidas por auth.session.
 * Sprint 4 — permission:products.read (EnsurePermission, segunda barrera local).
 */
Route::middleware(['auth.session', 'permission:products.read'])->prefix('products')->name('products.')->group(function () {
    Route::get('/', fn () => view('modules.products.index'))->name('index');
    Route::get('/prices', fn () => view('modules.products.prices'))->name('prices');
    Route::get('/zone-prices', fn () => view('modules.products.zone-prices'))->name('zone-prices');
});
