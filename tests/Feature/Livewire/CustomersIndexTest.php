<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Customers\Index;
use App\Services\CustomersService;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

/**
 * CustomersIndexTest
 *
 * Verifica la pantalla Clientes (Sprint 4 · Bloque C):
 * - Filtros y paginación se traducen al payload snake_case del contrato v2.
 * - Tabla vacía y estado de error honesto cuando la API falla.
 * - El permiso de ruta (customers.read) es la segunda barrera local.
 *
 * Nota: el render del componente consulta el catálogo desde el primer render,
 * por lo que todos los mocks deben tolerar la llamada inicial.
 */
class CustomersIndexTest extends TestCase
{
    private function mockService(string $response): Mockery\MockInterface
    {
        $service = Mockery::mock(CustomersService::class);
        $service->shouldReceive('list')->zeroOrMoreTimes()->andReturn(json_decode($response, true));
        $this->app->instance(CustomersService::class, $service);

        return $service;
    }

    public function test_renders_catalog_with_rows_and_meta_from_service_spy(): void
    {
        $this->mockService('{"success":true,"data":[{"clienteId":1001,"nombre":"Cliente Ejemplo","estatus":"A","zonaClienteId":3792,"zonaNombre":"SUR","vendedorId":695,"vendedorNombre":"María Hernández"}],"meta":{"page":1,"per_page":10,"total":1,"last_page":1}}');

        Livewire::test(Index::class)
            ->assertSee('Clientes')
            ->assertSee('Cliente Ejemplo')
            ->assertSee('SUR')
            ->assertSee('María Hernández')
            ->assertSee('Activo')
            ->assertSee('Página 1 de 1');
    }

    public function test_filters_are_sent_in_snake_case_and_clean_empty_values(): void
    {
        $service = $this->mockService('{"success":true,"data":[],"meta":{"page":1,"per_page":10,"total":0,"last_page":1}}');

        Livewire::test(Index::class)
            ->set('search', 'abarrotes')
            ->set('routeId', 695)
            ->set('status', 'A')
            ->call('consultar');

        $service->shouldHaveReceived('list')->with([
            'search' => 'abarrotes',
            'route_id' => 695,
            'status' => 'A',
            'page' => 1,
            'per_page' => 10,
        ]);
    }

    public function test_empty_table_shows_honest_empty_state(): void
    {
        $this->mockService('{"success":true,"data":[],"meta":{"page":1,"per_page":10,"total":0,"last_page":1}}');

        Livewire::test(Index::class)
            ->call('consultar')
            ->assertSee('Sin clientes para los filtros seleccionados');
    }

    public function test_api_error_shows_honest_error_state_and_not_zeroes(): void
    {
        $this->mockService('{"success":false,"code":"API_UNAVAILABLE","message":"No se pudo conectar con el servicio."}');

        Livewire::test(Index::class)
            ->call('consultar')
            ->assertSee('No se pudo consultar el catálogo')
            ->assertSee('No se pudo conectar con el servicio.')
            ->assertSee('Reintentar');
    }

    public function test_invalid_search_is_never_sent_to_the_api(): void
    {
        $captured = [];
        $service = Mockery::mock(CustomersService::class);
        $service->shouldReceive('list')->zeroOrMoreTimes()->andReturnUsing(
            function (array $payload) use (&$captured): array {
                $captured[] = $payload;

                return ['success' => true, 'data' => [], 'meta' => ['page' => 1, 'per_page' => 10, 'total' => 0, 'last_page' => 1]];
            }
        );
        $this->app->instance(CustomersService::class, $service);

        Livewire::test(Index::class)
            ->set('search', str_repeat('x', 201))
            ->call('consultar');

        $this->assertNotEmpty($captured);
        foreach ($captured as $payload) {
            $this->assertArrayNotHasKey('search', $payload);
        }
    }

    public function test_page_navigation_is_bounded_by_meta(): void
    {
        $this->mockService('{"success":true,"data":[],"meta":{"page":2,"per_page":10,"total":25,"last_page":3}}');

        Livewire::test(Index::class)
            ->set('page', 2)
            ->call('irAPagina', 1)
            ->assertSet('page', 1)
            ->call('irAPagina', 99)
            ->assertSet('page', 1)
            ->call('irAPagina', 0)
            ->assertSet('page', 1);
    }
}
