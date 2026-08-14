<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Routes\JornadaDelDia;
use App\Services\RouteMonitorService;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

/**
 * JornadaDelDiaTest
 *
 * Verifica la pantalla Ruta · Jornada del Día (Sprint 3 · Etapa 7):
 * - Modo @stub: renderiza el selector de rutas y la tabla con estado vacío.
 * - Con servicio espía: la timeline de RouteTimelineResponse se renderiza como
 *   data-table con status-badge (tipo de visita: Atendida / No venta).
 */
class JornadaDelDiaTest extends TestCase
{
    public function test_renders_route_selector_and_empty_table_in_stub_mode(): void
    {
        config()->set('services.api_web.stubs_enabled', true);

        Livewire::test(JornadaDelDia::class)
            ->assertSee('Jornada del Día')
            ->assertSee('Selecciona una ruta')
            ->assertSee('Ruta Norte')
            ->assertSee('Sin registros para este período')
            ->assertSee('wire:poll.20s', false);
    }

    public function test_timeline_rows_render_with_status_badges_from_service_spy(): void
    {
        $service = Mockery::mock(RouteMonitorService::class);
        $service->shouldReceive('routes')->zeroOrMoreTimes()->andReturn([
            'success' => true,
            'data' => [
                ['route_id' => 1, 'route_name' => 'Ruta Norte', 'zone_id' => 1, 'zone_name' => 'Zona Norte', 'status' => 'active'],
            ],
        ]);
        $service->shouldReceive('routeDetail')->once()->with(1)->andReturn([
            'success' => true,
            'data' => [
                'route_id' => 1,
                'seller' => 'María Hernández',
                'last_sale' => null,
                'timeline' => [
                    [
                        'at' => '2026-08-14T09:00:00-06:00',
                        'customer_name' => 'Cliente Ejemplo A',
                        'type' => 'visit',
                        'duration_minutes' => 15,
                        'amount' => 850.00,
                    ],
                    [
                        'at' => '2026-08-14T10:00:00-06:00',
                        'customer_name' => 'Cliente Ejemplo B',
                        'type' => 'no-sale',
                        'duration_minutes' => 5,
                        'amount' => null,
                    ],
                ],
                'status' => 'active',
            ],
        ]);
        $this->app->instance(RouteMonitorService::class, $service);

        Livewire::test(JornadaDelDia::class)
            ->set('routeId', 1)
            ->assertSee('Cliente Ejemplo A')
            ->assertSee('Atendida')
            ->assertSee('Cliente Ejemplo B')
            ->assertSee('No venta')
            ->assertSee('15 min')
            ->assertSee('$ 850.00');
    }

    public function test_detail_requires_a_route_selection(): void
    {
        $service = Mockery::mock(RouteMonitorService::class);
        $service->shouldReceive('routes')->once()->andReturn(['success' => true, 'data' => []]);
        $service->shouldNotReceive('routeDetail');
        $this->app->instance(RouteMonitorService::class, $service);

        Livewire::test(JornadaDelDia::class)
            ->assertSet('routeId', null)
            ->assertSee('Sin registros para este período');
    }
}
