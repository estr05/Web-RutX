<?php

use Illuminate\Support\Facades\Route;

/**
 * Rutas scaffold del módulo Clientes.
 * Día 2 — sin endpoint v2; solo estructura navegable.
 */
Route::prefix('customers')->name('customers.')->group(function () {
    Route::get('/', fn () => view('modules.customers.index'))->name('index');
    Route::get('/transfer', fn () => view('modules.customers.transfer'))->name('transfer');
});
