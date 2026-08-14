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
|   Las rutas scaffold, la redirección raíz (/) y el playground solo se
|   registran fuera del entorno de producción.
|   En producción (APP_ENV=production), la aplicación responde 404 a todas
|   estas rutas de forma natural sin intentar resolver rutas inexistentes.
|
|   Día 5 reemplazará este guard con:
|     Route::middleware(['auth.session'])->group(function () { ... });
|--------------------------------------------------------------------------
*/

if (! app()->isProduction()) {
    // Redirección de path fijo para evitar resolver nombres de ruta antes de tiempo
    Route::redirect('/', '/sales/reports-graphics')->name('home');

    // Módulos scaffold
    require __DIR__.'/modules/customers.php';
    require __DIR__.'/modules/products.php';
    require __DIR__.'/modules/inventory.php';
    require __DIR__.'/modules/sales.php';
    require __DIR__.'/modules/routes.php';
    require __DIR__.'/modules/settings.php';

    // Playground (fase fundacional — solo entorno de desarrollo/testing)
    require __DIR__.'/modules/playground.php';
}
