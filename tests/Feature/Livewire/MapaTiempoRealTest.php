<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Routes\MapaTiempoReal;
use App\Services\RouteMonitorService;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

/**
 * MapaTiempoRealTest
 *
 * Verifica la pantalla Ruta · Mapa en Tiempo Real (Sprint 3 · Etapa 7):
 * - Modo @stub: renderiza 3 marcadores de fixture con lat/lon numéricos,
 *   el panel lateral con KPIs y la cascada zona → ruta, sin HTTP real.
 * - Con servicio espía: los marcadores y KPIs reflejan la respuesta.
 * - El panel lateral colapsa con $sidePanelOpen.
 */
class MapaTiempoRealTest extends TestCase
{
    public function test_renders_three_stub_markers_and_side_panel(): void
    {
        config()->set('services.api_web.stubs_enabled', true);

        Livewire::test(MapaTiempoReal::class)
            ->assertSee('Mapa en Tiempo Real')
            ->assertSee('Estado de rutas')
            ->assertSee('Ruta Norte')
            ->assertSee('Ruta Sur')
            ->assertSee('Ruta Centro')
            ->assertSee('data-rutx-map', false)
            ->assertSee('data-markers', false)
            ->assertSee('wire:poll.20s', false)
            ->assertSee('20.663')
            ->assertSee('-103.352');
    }

    public function test_markers_and_kpis_render_from_service_spy(): void
    {
        $service = Mockery::mock(RouteMonitorService::class);
        $service->shouldReceive('monitor')->zeroOrMoreTimes()->andReturn([
            'success' => true,
            'data' => [
                [
                    'route_id' => 9,
                    'route_name' => 'Ruta Oriente',
                    'seller' => 'Luis Gómez',
                    'last_sale' => ['at' => '2026-08-14T12:00:00-06:00'],
                    'workday_started_at' => '2026-08-14T08:00:00-06:00',
                    'latitude' => 20.700,
                    'longitude' => -103.300,
                    'status' => 'active',
                ],
            ],
        ]);
        $service->shouldReceive('routes')->zeroOrMoreTimes()->andReturn([
            'success' => true,
            'data' => [
                ['route_id' => 9, 'route_name' => 'Ruta Oriente', 'zone_id' => 3, 'zone_name' => 'Zona Oriente', 'status' => 'active'],
            ],
        ]);
        $this->app->instance(RouteMonitorService::class, $service);

        Livewire::test(MapaTiempoReal::class)
            ->assertSee('Ruta Oriente')
            ->assertSee('Luis Gómez')
            ->assertSee('20.7')
            ->assertSee('-103.3')
            ->assertSee('Zona Oriente')
            ->assertSee('data-rutx-map', false);
    }

    public function test_side_panel_toggles_with_side_panel_open(): void
    {
        config()->set('services.api_web.stubs_enabled', true);

        Livewire::test(MapaTiempoReal::class)
            ->assertSet('sidePanelOpen', true)
            ->assertSee('Estado de rutas')
            ->set('sidePanelOpen', false)
            ->assertSet('sidePanelOpen', false)
            ->assertDontSee('Estado de rutas');
    }

    public function test_zone_filter_cascades_route_options(): void
    {
        config()->set('services.api_web.stubs_enabled', true);

        $component = Livewire::test(MapaTiempoReal::class);

        // Zona 1 tiene Ruta Norte y Ruta Centro; Zona 2 solo Ruta Sur.
        $component->set('zoneId', 2);
        $component->assertSee('Ruta Sur')
            ->assertDontSee('Ruta Norte');

        // Al cambiar de zona se limpia la ruta seleccionada.
        $component->set('zoneId', 1);
        $component->assertSet('routeId', null);
    }

    public function test_limpiar_resets_filters_and_keeps_side_panel(): void
    {
        config()->set('services.api_web.stubs_enabled', true);

        Livewire::test(MapaTiempoReal::class)
            ->set('zoneId', 2)
            ->set('routeId', 2)
            ->call('limpiar')
            ->assertSet('zoneId', null)
            ->assertSet('routeId', null)
            ->assertSet('sidePanelOpen', true);
    }
}
