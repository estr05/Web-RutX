<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Reports\ReportesGraficas;
use App\Services\DashboardService;
use App\Services\ReportsService;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

/**
 * ReportesGraficasTest
 *
 * Verifica la primera pantalla real del Sprint 3:
 * - Modo @stub: renderiza los 7 KPIs del contrato (todos en $ 0.00, sin
 *   cifras inventadas), gráfica vacía y tabla con estado vacío.
 * - Con servicios espía: los KPIs, la serie y los movimientos se renderizan
 *   cuando la respuesta del servicio es exitosa.
 * - limpiar() restablece los filtros a su valor inicial.
 */
class ReportesGraficasTest extends TestCase
{
    public function test_renders_seven_zero_kpis_in_stub_mode(): void
    {
        config()->set('services.api_web.stubs_enabled', true);

        Livewire::test(ReportesGraficas::class)
            ->assertSee('Venta total')
            ->assertSee('Contado')
            ->assertSee('Crédito')
            ->assertSee('Cobranza')
            ->assertSee('No ventas')
            ->assertSee('Entrega')
            ->assertSee('Gastos')
            ->assertSee('$ 0.00')
            ->assertSee('wire:poll.30s', false)
            ->assertSee('Sin registros para este período');
    }

    public function test_kpis_series_and_rows_render_from_service_spies(): void
    {
        $dashboard = Mockery::mock(DashboardService::class);
        $dashboard->shouldReceive('summary')->once()->andReturn([
            'success' => true,
            'data' => [
                'kpi' => [
                    ['label' => 'Venta total', 'value' => 15420.5, 'delta' => '+5.2%', 'status' => 'success'],
                    ['label' => 'Contado', 'value' => 1000.0, 'delta' => null, 'status' => 'unknown'],
                ],
            ],
        ]);
        $dashboard->shouldReceive('salesSeries')->once()->andReturn([
            'success' => true,
            'data' => [
                'series' => [
                    ['period' => '2026-08-14', 'amount' => 500.0],
                    ['period' => '2026-08-15', 'amount' => 700.0],
                ],
                'currency' => 'MXN',
                'status' => 'ok',
            ],
        ]);
        $this->app->instance(DashboardService::class, $dashboard);

        $reports = Mockery::mock(ReportsService::class);
        $reports->shouldReceive('report')->once()->andReturn([
            'success' => true,
            'data' => [
                'totals' => ['sales_amount' => 500.0, 'pieces' => 12, 'currency' => 'MXN'],
                'by_route' => [
                    [
                        'route_name' => 'Ruta Centro',
                        'pieces' => 12,
                        'cash_amount' => 500.0,
                        'credit_amount' => 0.0,
                        'total_amount' => 500.0,
                    ],
                ],
                'status' => 'ok',
            ],
        ]);
        $this->app->instance(ReportsService::class, $reports);

        Livewire::test(ReportesGraficas::class)
            ->assertSee('Venta total')
            ->assertSee('$ 15,420.50')
            ->assertSee('+5.2%')
            ->assertSee('Ruta Centro')
            ->assertSee('$ 500.00');
    }

    public function test_limpiar_resets_filters_to_initial_values(): void
    {
        config()->set('services.api_web.stubs_enabled', true);

        Livewire::test(ReportesGraficas::class)
            ->set('range', 'mensual')
            ->set('dateFrom', '2026-08-14')
            ->call('limpiar')
            ->assertSet('range', 'diario')
            ->assertSet('dateFrom', null);
    }
}
