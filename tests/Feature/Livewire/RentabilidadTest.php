<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Reports\Rentabilidad;
use App\Services\ReportsService;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

/**
 * RentabilidadTest
 *
 * Verifica la pantalla Venta · Rentabilidad por Ruta (Sprint 3 · Etapa 6):
 * - Modo @stub: renderiza los KPIs de agregados con valores cero/unknown
 *   (endpoint Posterior del contrato v2, aún no publicado por el Sincronizador).
 * - Con servicio espía: KPIs, gráfica y tabla de rentabilidad por ruta.
 */
class RentabilidadTest extends TestCase
{
    public function test_renders_rentabilidad_in_stub_mode(): void
    {
        config()->set('services.api_web.stubs_enabled', true);

        Livewire::test(Rentabilidad::class)
            ->assertSee('Rentabilidad por Ruta')
            ->assertSee('Venta total')
            ->assertSee('Gastos')
            ->assertSee('Costo disponible')
            ->assertSee('Utilidad')
            ->assertSee('$ 0.00')
            ->assertSee('wire:poll.30s', false)
            ->assertSee('Sin registros para este período');
    }

    public function test_kpis_chart_and_rows_render_from_service_spy(): void
    {
        $reports = Mockery::mock(ReportsService::class);
        $reports->shouldReceive('routeProfitability')->once()->andReturn([
            'success' => true,
            'data' => [
                'totals' => [
                    'sales_amount' => 10000.0,
                    'expense_amount' => 2000.0,
                    'delivery_amount' => 500.0,
                    'cost_amount' => 6000.0,
                    'profit_amount' => 1500.0,
                    'currency' => 'MXN',
                ],
                'by_route' => [
                    [
                        'route_name' => 'Ruta Centro',
                        'sales_amount' => 10000.0,
                        'expense_amount' => 2000.0,
                        'delivery_amount' => 500.0,
                        'cost_amount' => 6000.0,
                        'profit_amount' => 1500.0,
                    ],
                ],
                'status' => 'ok',
            ],
        ]);
        $this->app->instance(ReportsService::class, $reports);

        Livewire::test(Rentabilidad::class)
            ->assertSee('Venta total')
            ->assertSee('$ 10,000.00')
            ->assertSee('Utilidad')
            ->assertSee('$ 1,500.00')
            ->assertSee('Ruta Centro')
            ->assertSee('data-rutx-chart', false);
    }

    public function test_limpiar_resets_filters_to_initial_values(): void
    {
        config()->set('services.api_web.stubs_enabled', true);

        Livewire::test(Rentabilidad::class)
            ->set('range', 'diario')
            ->set('routeId', 3)
            ->call('limpiar')
            ->assertSet('range', 'mensual')
            ->assertSet('routeId', null)
            ->assertSet('zoneId', null);
    }
}
