<?php

use App\Http\Controllers\Pages\NotificationsController;
use Illuminate\Support\Facades\Route;

/**
 * Rutas del módulo Notificaciones — Sprint 4.
 * Protegidas por auth.session (JWT web en sesión cifrada) y por el permiso
 * notifications.read (EnsurePermission, segunda barrera local). El estado
 * de pantalla vive en Livewire; el controlador solo retorna view().
 */
Route::middleware(['auth.session', 'permission:notifications.read'])
    ->prefix('notifications')
    ->name('notifications.')
    ->group(function () {
        Route::get('/', [NotificationsController::class, 'index'])->name('index');
    });
