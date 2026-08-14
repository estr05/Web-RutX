<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas Web — RutX Web
|--------------------------------------------------------------------------
| Este archivo actúa como punto de registro, no como catálogo de pantallas.
| Cada módulo declara sus rutas en routes/modules/<modulo>.php.
|
| BARRERA DE DESPLIEGUE (Sprint 2 → Sprint 3):
|   Las rutas scaffold, la redirección raíz (/) y el playground solo se
|   registran fuera del entorno de producción (APP_ENV=production → 404).
|   Los módulos de negocio del Sprint 3 (venta, ruta) ya van protegidos por
|   auth.session; la barrera se levanta cuando llegue el login real, que
|   sustituirá el guard por: Route::middleware(['auth.session'])->group(...).
|--------------------------------------------------------------------------
*/

if (! app()->isProduction()) {
    // Redirección de path fijo para evitar resolver nombres de ruta antes de tiempo
    Route::redirect('/', '/venta')->name('home');

    // Módulos scaffold (Día 2 — estructura navegable)
    require __DIR__.'/modules/customers.php';
    require __DIR__.'/modules/products.php';
    require __DIR__.'/modules/inventory.php';
    require __DIR__.'/modules/settings.php';

    // Módulos de negocio del Sprint 3 — protegidos por sesión cifrada
    require __DIR__.'/modules/venta.php';
    require __DIR__.'/modules/ruta.php';

    // Autenticación (placeholder — vertical real en etapa posterior)
    require __DIR__.'/modules/auth.php';

    // Playground (fase fundacional — solo entorno de desarrollo/testing)
    require __DIR__.'/modules/playground.php';
}
