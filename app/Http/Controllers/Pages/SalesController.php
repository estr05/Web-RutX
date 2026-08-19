<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pages;

use App\Http\Controllers\Controller;

/**
 * Controlador de página del módulo Venta (Sprint 3).
 * Métodos de una línea que solo retornan view(); todo el estado de pantalla
 * vive en los componentes Livewire (guidelines §2.2).
 */
class SalesController extends Controller
{
    public function reportesGraficas()
    {
        return view('modules.venta.reportes-graficas');
    }

    public function reportesGlobales()
    {
        return view('modules.venta.reportes-globales');
    }

    public function rentabilidad()
    {
        return view('modules.venta.rentabilidad');
    }

    public function transacciones()
    {
        return view('modules.venta.transacciones');
    }

    public function detalle(int $id)
    {
        return view('modules.venta.detalle', ['saleId' => $id]);
    }
}
