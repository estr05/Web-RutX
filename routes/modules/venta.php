<?php

use App\Http\Controllers\Pages\SalesController;
use Illuminate\Support\Facades\Route;

/**
 * Rutas del módulo Venta — Sprint 3.
 * Protegidas por auth.session (JWT web en sesión cifrada) y permission:reports.read
 * (EnsurePermission, segunda barrera local).
 * Controladores de página de una línea; el estado vive en Livewire.
 */
Route::middleware(['auth.session', 'permission:reports.read'])->prefix('venta')->name('venta.')->group(function () {
    Route::get('/', [SalesController::class, 'reportesGraficas'])->name('reportes');
    Route::get('/reportes-globales', [SalesController::class, 'reportesGlobales'])->name('globales');
    Route::get('/rentabilidad', [SalesController::class, 'rentabilidad'])->name('rentabilidad');
});
