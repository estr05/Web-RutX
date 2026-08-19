<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pages;

use App\Http\Controllers\Controller;

/**
 * Controlador de página del módulo Notificaciones (Sprint 4).
 * Método de una línea que solo retorna view(); todo el estado de pantalla
 * vive en el componente Livewire (guidelines §2.2).
 */
class NotificationsController extends Controller
{
    public function index()
    {
        return view('modules.notifications.index');
    }
}
