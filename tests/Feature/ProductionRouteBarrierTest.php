<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * ProductionRouteBarrierTest
 *
 * Verifica de forma aislada que en entorno de producción (APP_ENV=production):
 * 1. `php artisan route:list --json` NO contiene rutas scaffold de módulos ni playground.
 * 2. `/` y `/playground` retornan 404 de forma natural (no 500 ni redirección).
 *
 * Estrategia: se inician subprocesos PHP separados con APP_ENV=production antes del
 * bootstrap, por lo que el guard `if (! app()->isProduction())` en routes/web.php
 * evalúa el entorno correcto. El proceso padre (PHPUnit) corre con APP_ENV=testing.
 */
class ProductionRouteBarrierTest extends TestCase
{
    public function test_production_environment_does_not_register_scaffold_or_playground_routes(): void
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
        $routeUris = array_column($routes, 'uri');

        // Sprint 3 · Etapa 2: los módulos de negocio ahora son venta./ruta. y
        // el resto de los scaffolds (customers/products/inventory/settings)
        // y el playground se mantienen fuera de producción.
        $disallowedPrefixes = ['customers.', 'products.', 'inventory.', 'venta.', 'ruta.', 'settings.', 'playground'];

        foreach ($routeNames as $name) {
            foreach ($disallowedPrefixes as $prefix) {
                $this->assertStringStartsNotWith(
                    $prefix,
                    $name,
                    "La ruta '{$name}' no debe estar registrada en entorno de producción.",
                );
            }
        }

        $this->assertNotContains('playground', $routeUris, 'La URI playground no debe estar en producción.');
    }

    public function test_production_environment_returns_404_on_root_and_playground(): void
    {
        $process = Process::path(base_path())
            ->env(['APP_ENV' => 'production'])
            ->run([
                PHP_BINARY,
                base_path('tests/Fixtures/production-route-barrier.php'),
            ]);

        $this->assertTrue(
            $process->successful(),
            'El subproceso PHP de producción falló. stderr: '.$process->errorOutput(),
        );

        $output = $process->output();

        $this->assertStringContainsString(
            'ROOT:404',
            $output,
            'En producción / debe responder HTTP 404. Salida completa: '.$output,
        );
        $this->assertStringContainsString(
            'PLAYGROUND:404',
            $output,
            'En producción /playground debe responder HTTP 404. Salida completa: '.$output,
        );
    }
}
