<?php

use Illuminate\Support\Facades\Route;

/**
 * Rutas scaffold del módulo Venta (15 vistas).
 * La entrada predeterminada del módulo es sales.reports-graphics.
 * Día 2 — sin endpoint v2; solo estructura navegable.
 */
Route::prefix('sales')->name('sales.')->group(function () {
    // Vista predeterminada del módulo y entrada inicial del chasis
    Route::get('/reports-graphics', fn () => view('modules.sales.reports-graphics'))->name('reports-graphics');
    Route::get('/global-reports', fn () => view('modules.sales.global-reports'))->name('global-reports');
    Route::get('/survey', fn () => view('modules.sales.survey'))->name('survey');
    Route::get('/orders', fn () => view('modules.sales.orders'))->name('orders');
    Route::get('/collections', fn () => view('modules.sales.collections'))->name('collections');
    Route::get('/profitability', fn () => view('modules.sales.profitability'))->name('profitability');
    Route::get('/deposit', fn () => view('modules.sales.deposit'))->name('deposit');
    Route::get('/expense', fn () => view('modules.sales.expense'))->name('expense');
    Route::get('/customer-report', fn () => view('modules.sales.customer-report'))->name('customer-report');
    Route::get('/profitability-route', fn () => view('modules.sales.profitability-route'))->name('profitability-route');
    Route::get('/top-customers', fn () => view('modules.sales.top-customers'))->name('top-customers');
    Route::get('/rejected-products', fn () => view('modules.sales.rejected-products'))->name('rejected-products');
    Route::get('/pre-delivery', fn () => view('modules.sales.pre-delivery'))->name('pre-delivery');
    Route::get('/pending-customers', fn () => view('modules.sales.pending-customers'))->name('pending-customers');
    Route::get('/viewer', fn () => view('modules.sales.viewer'))->name('viewer');
});
