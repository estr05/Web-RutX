<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

/**
 * Autenticación de oficina (Sprint 4 · Bloque 0).
 *
 * - El login es el ÚNICO endpoint público de la frontera web; el resto de
 *   módulos va protegido por auth.session (+ permission:* por vertical).
 * - POST /login lleva throttle local (5 intentos/minuto) como primera
 *   barrera; el Sincronizador aplica su propio rate limit por IP.
 * - POST /logout exige sesión activa y solo invalida la sesión cifrada
 *   local (la revocación del token vía /auth/logout es posterior en el
 *   contrato v2 §5).
 */
Route::get('/login', [LoginController::class, 'show'])->name('login');

Route::post('/login', [LoginController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('login.store');

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth.session')
    ->name('logout');
