<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas Web — RutX Web
|--------------------------------------------------------------------------
| Este archivo actúa como punto de registro, no como catálogo de pantallas.
| Cada módulo declara sus rutas en routes/modules/<module>.php.
|
| BARRERA DE DESPLIEGUE (Día 2 → Día 5):
|   Las rutas scaffold no están protegidas con auth.session porque la
|   autenticación web se implementa en el Día 5. En este intervalo,
|   las rutas de módulo solo se registran fuera del entorno de producción.
|   En producción el usuario solo verá un 404 hasta que auth.session esté
|   activo y las rutas se envuelvan en su middleware correspondiente.
|
|   Para habilitar en producción, envolver el bloque de módulos con:
|     Route::middleware(['auth.session'])->group(function () { ... });
|   y eliminar el guard de entorno.
|--------------------------------------------------------------------------
*/

// Entrada inicial: Venta · Reportes y Gráficas
Route::get('/', fn () => redirect()->route('sales.reports-graphics'));

// Rutas scaffold de módulos — accesibles solo fuera de producción hasta Día 5
if (! app()->isProduction()) {
    require __DIR__ . '/modules/customers.php';
    require __DIR__ . '/modules/products.php';
    require __DIR__ . '/modules/inventory.php';
    require __DIR__ . '/modules/sales.php';
    require __DIR__ . '/modules/routes.php';
    require __DIR__ . '/modules/settings.php';
}

// Playground (fase fundacional)
require __DIR__ . '/modules/playground.php';
