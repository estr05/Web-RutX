<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pages;

use App\Http\Controllers\Controller;

/**
 * Controlador de página del módulo Ruta (Sprint 3).
 * Métodos de una línea que solo retornan view(); el monitoreo en vivo
 * (polling, mapa Leaflet) vive en los componentes Livewire.
 */
class RouteController extends Controller
{
    public function mapa()
    {
        return view('modules.ruta.mapa');
    }

    public function jornada()
    {
        return view('modules.ruta.jornada');
    }

    public function agenda()
    {
        return view('modules.ruta.agenda');
    }
}
