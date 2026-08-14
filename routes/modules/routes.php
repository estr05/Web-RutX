<?php

use Illuminate\Support\Facades\Route;

/**
 * Rutas scaffold del módulo Ruta.
 * Agenda, mapa y jornada son scaffold puro: sin polling, tabla,
 * drag & drop ni llamada API. Pertenecen a sprints posteriores.
 * Día 2 — sin endpoint v2; solo estructura navegable.
 */
Route::prefix('routes')->name('routes.')->group(function () {
    Route::get('/map', fn () => view('modules.routes.map'))->name('map');
    Route::get('/workday', fn () => view('modules.routes.workday'))->name('workday');
    Route::get('/agenda', fn () => view('modules.routes.agenda'))->name('agenda');
    Route::get('/mileage', fn () => view('modules.routes.mileage'))->name('mileage');
});
