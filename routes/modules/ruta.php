<?php

use App\Http\Controllers\Pages\RouteController;
use Illuminate\Support\Facades\Route;

/**
 * Rutas del módulo Ruta — Sprint 3.
 * Protegidas por auth.session y permission:routes.monitor (EnsurePermission,
 * segunda barrera local). El monitoreo con polling 10–30 s y el mapa
 * Leaflet se integran en el componente Livewire de etapas posteriores.
 */
Route::middleware(['auth.session', 'permission:routes.monitor'])->prefix('ruta')->name('ruta.')->group(function () {
    Route::get('/', [RouteController::class, 'mapa'])->name('mapa');
    Route::get('/jornada', [RouteController::class, 'jornada'])->name('jornada');
});
