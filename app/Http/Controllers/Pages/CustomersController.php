<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pages;

use App\Http\Controllers\Controller;

/**
 * Controlador de página del módulo Clientes (Sprint 4).
 * Métodos de una línea que solo retornan view(); todo el estado de pantalla
 * vive en el componente Livewire (guidelines §2.2).
 */
class CustomersController extends Controller
{
    public function index()
    {
        return view('modules.customers.index');
    }
}
