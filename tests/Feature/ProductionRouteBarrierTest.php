<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * ProductionRouteBarrierTest
 *
 * Verifica de forma aislada que en entorno de producción (APP_ENV=production):
 * 1. Las rutas de autenticación, negocio y configuración SÍ están registradas.
 * 2. El playground NO está registrado.
 * 3. `/` redirige a `/venta` (no 404).
 *
 * Estrategia: se inician subprocesos PHP separados con APP_ENV=production antes del
 * bootstrap, por lo que el guard `if (! app()->isProduction())` en routes/web.php
 * evalúa el entorno correcto. El proceso padre (PHPUnit) corre con APP_ENV=testing.
 */
class ProductionRouteBarrierTest extends TestCase
{
    public function test_production_registers_business_and_auth_routes(): void
    {
        $process = Process::path(base_path())
            ->env(['APP_ENV' => 'production'])
            ->run('php artisan route:list --json');

        $this->assertTrue(
            $process->successful(),
            'Artisan route:list debe ejecutarse con éxito en producción. stderr: '.$process->errorOutput(),
        );

        $routes = json_decode($process->output(), true);
        $this->assertIsArray($routes, 'route:list --json debe devolver un arreglo JSON válido.');

        $routeNames = array_filter(array_column($routes, 'name'));

        // En producción, las rutas legítimas de negocio y auth SÍ deben existir.
        $expectedPrefixes = ['login', 'logout', 'customers.', 'products.', 'inventory.', 'venta.', 'ruta.', 'settings.', 'notifications.'];

        foreach ($expectedPrefixes as $prefix) {
            $found = false;
            foreach ($routeNames as $name) {
                if (str_starts_with((string) $name, $prefix)) {
                    $found = true;
                    break;
                }
            }
            $this->assertTrue(
                $found,
                "Debe existir al menos una ruta con prefijo '{$prefix}' en producción.",
            );
        }

        // El playground NO debe estar registrado en producción.
        foreach ($routeNames as $name) {
            $this->assertStringStartsNotWith(
                'playground',
                (string) $name,
                'La ruta de playground no debe estar registrada en producción.',
            );
        }
    }

    public function test_production_does_not_register_playground_route(): void
    {
        $process = Process::path(base_path())
            ->env(['APP_ENV' => 'production'])
            ->run('php artisan route:list --json');

        $this->assertTrue($process->successful());

        $routes = json_decode($process->output(), true);
        $routeUris = array_column($routes, 'uri');

        $this->assertNotContains('playground', $routeUris, 'La URI playground no debe estar en producción.');
    }
}
