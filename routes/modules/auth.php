<?php

use Illuminate\Support\Facades\Route;

/**
 * Autenticación — placeholder de oficina (Sprint 3 · Etapa 2).
 * Existe únicamente la ruta destino de auth.session (login) para que la
 * redirección de sesión caducada no falle. El vertical real (Form Request,
 * POST /api/v2/web/auth/login, sesión cifrada) llega en una etapa posterior.
 */
Route::get('/login', fn () => view('modules.auth.login'))->name('login');
