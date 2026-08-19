<?php

use Illuminate\Support\Facades\Route;

/**
 * Rutas del módulo Configuración.
 * Sprint 3 — protegidas por auth.session.
 * Sprint 4 — permission:config.users.read (EnsurePermission, segunda barrera local).
 */
Route::middleware(['auth.session', 'permission:config.users.read'])->prefix('settings')->name('settings.')->group(function () {
    Route::get('/users', fn () => view('modules.settings.users'))->name('users');
    Route::get('/roles', fn () => view('modules.settings.roles'))->name('roles');
    Route::get('/zones', fn () => view('modules.settings.zones'))->name('zones');
});
