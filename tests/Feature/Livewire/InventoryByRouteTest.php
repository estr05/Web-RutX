<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Inventory\ByRoute;
use App\Services\InventoryService;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

/**
 * InventoryByRouteTest
 *
 * Verifica la pantalla Inventario por Ruta — piloto (Sprint 4 · Bloque C):
 * - Sin consulta previa muestra el estado neutral (no inventa datos).
 * - La ruta es obligatoria antes de llamar a la API.
 * - La tabla del piloto usa productId/productName/available/sold del contrato.
 * - Un error de API se muestra honestamente (p. ej. NOT_FOUND o API_UNAVAILABLE).
 */
class InventoryByRouteTest extends TestCase
{
    public function test_neutral_state_before_first_consultation(): void
    {
        $service = Mockery::mock(InventoryService::class);
        $service->shouldNotReceive('byRoute');
        $this->app->instance(InventoryService::class, $service);

        Livewire::test(ByRoute::class)
            ->assertSee('Selecciona una ruta para consultar el piloto de inventario.');
    }

    public function test_route_is_required_before_calling_the_api(): void
    {
        $service = Mockery::mock(InventoryService::class);
        $service->shouldNotReceive('byRoute');
        $this->app->instance(InventoryService::class, $service);

        Livewire::test(ByRoute::class)
            ->call('consultar')
            ->assertHasErrors(['route_id'])
            ->assertSet('consulted', false);
    }

    public function test_pilot_table_renders_rows_from_service_spy(): void
    {
        $service = Mockery::mock(InventoryService::class);
        $service->shouldReceive('byRoute')->zeroOrMoreTimes()->andReturn([
            'success' => true,
            'data' => [
                [
                    'productId' => 9673,
                    'productCode' => '0001',
                    'productName' => 'CHAMOY',
                    'unit' => 'PZA',
                    'available' => 150,
                    'sold' => 40,
                    'difference' => 110,
                ],
            ],
            'meta' => ['page' => 1, 'per_page' => 25, 'total' => 1, 'last_page' => 1],
        ]);
        $this->app->instance(InventoryService::class, $service);

        Livewire::test(ByRoute::class)
            ->set('routeId', 695)
            ->call('consultar')
            ->assertSet('consulted', true)
            ->assertSee('CHAMOY')
            ->assertSee('PZA')
            ->assertSee('Página 1 de 1');
    }

    public function test_api_error_is_shown_honestly_and_recoverable(): void
    {
        $service = Mockery::mock(InventoryService::class);
        $service->shouldReceive('byRoute')->zeroOrMoreTimes()->andReturn([
            'success' => false,
            'code' => 'NOT_FOUND',
            'message' => 'La ruta no tiene un almacén de operación reciente (últimos 90 días).',
        ]);
        $this->app->instance(InventoryService::class, $service);

        Livewire::test(ByRoute::class)
            ->set('routeId', 99999)
            ->call('consultar')
            ->assertSee('No se pudo consultar el inventario')
            ->assertSee('La ruta no tiene un almacén de operación reciente')
            ->assertSee('Reintentar');
    }

    public function test_forbidden_zone_is_reported_without_emptying_the_table(): void
    {
        $service = Mockery::mock(InventoryService::class);
        $service->shouldReceive('byRoute')->zeroOrMoreTimes()->andReturn([
            'success' => false,
            'code' => 'FORBIDDEN_ZONE',
            'message' => 'La zona solicitada no está dentro de las zonas autorizadas de tu usuario.',
        ]);
        $this->app->instance(InventoryService::class, $service);

        Livewire::test(ByRoute::class)
            ->set('routeId', 695)
            ->set('zoneId', 3795)
            ->call('consultar')
            ->assertSee('No se pudo consultar el inventario')
            ->assertSee('La zona solicitada no está dentro de las zonas autorizadas')
            ->assertDontSee('Sin movimientos');
    }
}
