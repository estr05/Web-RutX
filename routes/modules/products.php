<?php

use Illuminate\Support\Facades\Route;

/**
 * Rutas scaffold del módulo Productos.
 * Día 2 — sin endpoint v2; solo estructura navegable.
 * Sprint 3 — protegidas por auth.session.
 */
Route::middleware('auth.session')->prefix('products')->name('products.')->group(function () {
    Route::get('/', fn () => view('modules.products.index'))->name('index');
    Route::get('/prices', fn () => view('modules.products.prices'))->name('prices');
    Route::get('/zone-prices', fn () => view('modules.products.zone-prices'))->name('zone-prices');
});
