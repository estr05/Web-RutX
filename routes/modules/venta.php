<?php

use App\Http\Controllers\Pages\SalesController;
use Illuminate\Support\Facades\Route;

/**
 * Rutas del módulo Venta — Sprint 3.
 * Protegidas por auth.session (JWT web en sesión cifrada) y permission:reports.read
 * (EnsurePermission, segunda barrera local).
 * Controladores de página de una línea; el estado vive en Livewire.
 */
Route::middleware(['auth.session'])->prefix('venta')->name('venta.')->group(function () {
    Route::get('/', [SalesController::class, 'reportesGraficas'])
        ->name('reportes')
        ->middleware('permission:reports.read');

    Route::get('/reportes-globales', [SalesController::class, 'reportesGlobales'])
        ->name('globales')
        ->middleware('permission:reports.read');

    Route::get('/rentabilidad', [SalesController::class, 'rentabilidad'])
        ->name('rentabilidad')
        ->middleware('permission:reports.read');

    // Nuevas rutas Sprint 5 - Fase 4
    Route::get('/transacciones', [SalesController::class, 'transacciones'])
        ->name('transacciones')
        ->middleware('permission:sales.read');

    Route::get('/transacciones/{id}', [SalesController::class, 'detalle'])
        ->name('detalle')
        ->middleware('permission:sales.read');
});
