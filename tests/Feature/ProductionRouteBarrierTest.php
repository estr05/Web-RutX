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
            'Artisan route:list debe ejecutarse con éxito en producción.',
        );

        $routes = json_decode($process->output(), true);
        $this->assertIsArray($routes, 'route:list --json debe devolver un arreglo.');

        $routeNames = array_filter(array_column($routes, 'name'));
        $routeUris = array_column($routes, 'uri');

        $disallowedPrefixes = ['customers.', 'products.', 'inventory.', 'sales.', 'routes.', 'settings.', 'playground'];
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
        $command = 'php -r "'.
            'require \'vendor/autoload.php\'; '.
            '$app = require \'bootstrap/app.php\'; '.
            '$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class); '.
            '$res1 = $kernel->handle(Illuminate\Http\Request::create(\'/\', \'GET\')); '.
            '$res2 = $kernel->handle(Illuminate\Http\Request::create(\'/playground\', \'GET\')); '.
            'echo \'ROOT:\' . $res1->getStatusCode() . \' PLAYGROUND:\' . $res2->getStatusCode();"';

        $process = Process::path(base_path())
            ->env(['APP_ENV' => 'production'])
            ->run($command);

        $output = $process->output();

        $this->assertStringContainsString('ROOT:404', $output, 'En producción / debe responder HTTP 404.');
        $this->assertStringContainsString('PLAYGROUND:404', $output, 'En producción /playground debe responder HTTP 404.');
    }
}
