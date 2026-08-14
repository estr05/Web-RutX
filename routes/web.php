<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas Web — RutX Web
|--------------------------------------------------------------------------
| Este archivo actúa como punto de registro, no como catálogo de pantallas.
| Cada módulo declara sus rutas en routes/modules/<module>.php.
|
| Día 2: rutas scaffold sin middleware auth.session (pendiente Día 5).
| Cuando se integre autenticación, el grupo de módulos se envolverá en
| ->middleware(['auth.session']) sin modificar los archivos de módulo.
|--------------------------------------------------------------------------
*/

// Entrada inicial: Venta · Reportes y Gráficas
Route::get('/', fn () => redirect()->route('sales.reports-graphics'));

// Módulos scaffold
require __DIR__ . '/modules/customers.php';
require __DIR__ . '/modules/products.php';
require __DIR__ . '/modules/inventory.php';
require __DIR__ . '/modules/sales.php';
require __DIR__ . '/modules/routes.php';
require __DIR__ . '/modules/settings.php';

// Playground (fase fundacional — no exponer en producción)
require __DIR__ . '/modules/playground.php';
