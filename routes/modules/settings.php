<?php

use Illuminate\Support\Facades\Route;

/**
 * Rutas scaffold del módulo Configuración.
 * Sin CRUD, roles reales ni configuración del Sincronizador.
 * Día 2 — sin endpoint v2; solo estructura navegable.
 * Sprint 3 — protegidas por auth.session.
 */
Route::middleware('auth.session')->prefix('settings')->name('settings.')->group(function () {
    Route::get('/users', fn () => view('modules.settings.users'))->name('users');
    Route::get('/roles', fn () => view('modules.settings.roles'))->name('roles');
    Route::get('/zones', fn () => view('modules.settings.zones'))->name('zones');
});
