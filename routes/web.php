<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas Web — RutX Web
|--------------------------------------------------------------------------
| Este archivo actúa como punto de registro, no como catálogo de pantallas.
| Cada módulo declara sus rutas en routes/modules/<modulo>.php.
|
| PRODUCCIÓN (Sprint 4):
|   Las rutas de autenticación, negocio y configuración se registran SIEMPRE.
|   Están protegidas por auth.session y permission:* en cada módulo.
|   El playground solo se registra fuera de producción.
|--------------------------------------------------------------------------
*/

// Redirección de path fijo
Route::redirect('/', '/venta')->name('home');

// Autenticación — login es el único endpoint público (no requiere sesión)
require __DIR__.'/modules/auth.php';

// Módulos de negocio — protegidos por auth.session + permission:*
require __DIR__.'/modules/customers.php';
require __DIR__.'/modules/products.php';
require __DIR__.'/modules/inventory.php';
require __DIR__.'/modules/settings.php';
require __DIR__.'/modules/venta.php';
require __DIR__.'/modules/ruta.php';

// Módulos de negocio del Sprint 4 — auth.session + permission
require __DIR__.'/modules/notifications.php';

// Playground — solo fuera de producción (fase fundacional)
if (! app()->isProduction()) {
    require __DIR__.'/modules/playground.php';
}
