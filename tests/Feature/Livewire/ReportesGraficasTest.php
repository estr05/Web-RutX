<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Reports\ReportesGraficas;
use App\Services\DashboardService;
use App\Services\ReportsService;
use Livewire\Livewire;
use Mockery;
use Tests\Fixtures\VisualDashboardFixture;
use Tests\TestCase;

/**
 * ReportesGraficasTest
 *
 * Verifica la pantalla de Venta · Reportes y Gráficas:
 * - Modo @stub: renderiza los 7 KPIs del contrato con formato explícito
 *   (6 monetarios en $ 0.00 y No ventas en 0), estado vacío accesible en gráfica y tabla.
 * - Single call check: garantiza que DashboardService::summary() y ReportsService::report()
 *   se invocan exactamente UNA VEZ por render (sin duplicación HTTP).
 * - routeChart: mapea correctamente Contado y Crédito con sus tokens CSS respectivos.
 * - Fixture visual: renderizado de montos grandes, subdesgloses de Entrega y footer de totales.
 * - limpiar() restablece los filtros a su valor inicial.
 */
class ReportesGraficasTest extends TestCase
{
    public function test_renders_seven_kpis_with_correct_formats_in_stub_mode(): void
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
            ->assertSee('Sin datos para el período seleccionado')
            ->assertSee('Sin registros para este período')
            ->assertSee('wire:poll.30s', false);
    }

    public function test_services_are_called_exactly_once_per_render(): void
    {
        $dashboard = Mockery::mock(DashboardService::class);
        $dashboard->shouldReceive('summary')
            ->once()
            ->andReturn([
                'success' => true,
                'data' => [
                    'kpi' => [
                        ['label' => 'Venta total', 'value' => 15420.50, 'delta' => '+5.2%', 'status' => 'success'],
                        ['label' => 'No ventas', 'value' => 7, 'delta' => null, 'status' => 'error'],
                    ],
                    'meta' => ['currency' => 'MXN', 'last_sync_at' => null],
                ],
            ]);
        $this->app->instance(DashboardService::class, $dashboard);

        $reports = Mockery::mock(ReportsService::class);
        $reports->shouldReceive('report')
            ->once()
            ->andReturn([
                'success' => true,
                'data' => [
                    'totals' => ['sales_amount' => 15420.50, 'pieces' => 45, 'currency' => 'MXN'],
                    'by_route' => [
                        [
                            'route_name' => 'Ruta Centro',
                            'pieces' => 45,
                            'cash_amount' => 10000.00,
                            'credit_amount' => 5420.50,
                            'total_amount' => 15420.50,
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
            ->assertSee('No ventas')
            ->assertSee('7')
            ->assertDontSee('$ 7.00')
            ->assertSee('Ruta Centro')
            ->assertSee('Total período');
    }

    public function test_route_chart_computes_dual_series_with_tokens(): void
    {
        $dashboard = Mockery::mock(DashboardService::class);
        $dashboard->shouldReceive('summary')->andReturn(['success' => true, 'data' => ['kpi' => []]]);
        $this->app->instance(DashboardService::class, $dashboard);

        $reports = Mockery::mock(ReportsService::class);
        $reports->shouldReceive('report')->andReturn([
            'success' => true,
            'data' => [
                'totals' => ['sales_amount' => 1000.0, 'pieces' => 10],
                'by_route' => [
                    ['route_name' => 'Ruta Norte', 'pieces' => 5, 'cash_amount' => 400.0, 'credit_amount' => 200.0, 'total_amount' => 600.0],
                    ['route_name' => 'Ruta Sur', 'pieces' => 5, 'cash_amount' => 300.0, 'credit_amount' => 100.0, 'total_amount' => 400.0],
                ],
            ],
        ]);
        $this->app->instance(ReportsService::class, $reports);

        $component = Livewire::test(ReportesGraficas::class);
        $chart = $component->instance()->routeChart;

        $this->assertSame(['Ruta Norte', 'Ruta Sur'], $chart['labels']);
        $this->assertCount(2, $chart['datasets']);
        $this->assertSame('Contado', $chart['datasets'][0]['label']);
        $this->assertSame([400.0, 300.0], $chart['datasets'][0]['data']);
        $this->assertSame('--rutx-chart-blue', $chart['datasets'][0]['colorToken']);
        $this->assertSame('Crédito', $chart['datasets'][1]['label']);
        $this->assertSame([200.0, 100.0], $chart['datasets'][1]['data']);
        $this->assertSame('--rutx-chart-cyan', $chart['datasets'][1]['colorToken']);
    }

    public function test_renders_visual_fixture_with_large_amounts_sublines_and_details(): void
    {
        $dashboard = Mockery::mock(DashboardService::class);
        $dashboard->shouldReceive('summary')->andReturn(VisualDashboardFixture::summary());
        $this->app->instance(DashboardService::class, $dashboard);

        $reports = Mockery::mock(ReportsService::class);
        $reports->shouldReceive('report')->andReturn(VisualDashboardFixture::report());
        $this->app->instance(ReportsService::class, $reports);

        Livewire::test(ReportesGraficas::class)
            ->assertSee('$ 669,558.99')
            ->assertSee('Contado + Crédito')
            ->assertSee('$ 450,200.00')
            ->assertSee('$ 219,358.99')
            ->assertSee('7')
            ->assertDontSee('$ 7.00')
            ->assertSee('$ 74,000.00')
            ->assertSee('$ 38,000.00')
            ->assertSee('Ruta Norte')
            ->assertSee('Ruta Sur')
            ->assertSee('Ruta Centro')
            ->assertSee('Total período');
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

    public function test_get_totals_calculates_fallback_if_api_totals_is_empty(): void
    {
        $dashboard = Mockery::mock(DashboardService::class);
        $dashboard->shouldReceive('summary')->andReturn(['success' => true, 'data' => ['kpi' => []]]);
        $this->app->instance(DashboardService::class, $dashboard);

        $reports = Mockery::mock(ReportsService::class);
        $reports->shouldReceive('report')->andReturn([
            'success' => true,
            'data' => [
                'totals' => [], // API omits totals
                'by_route' => [
                    ['route_name' => 'A', 'pieces' => 2, 'cash_amount' => 10.0, 'credit_amount' => 5.0, 'total_amount' => 15.0],
                    ['route_name' => 'B', 'pieces' => 3, 'cash_amount' => 20.0, 'credit_amount' => 10.0, 'total_amount' => 30.0],
                ],
            ],
        ]);
        $this->app->instance(ReportsService::class, $reports);

        $component = Livewire::test(ReportesGraficas::class);
        $totals = $component->instance()->totals;

        $this->assertEquals(5, $totals['pieces']);
        $this->assertEquals(30.0, $totals['cash_amount']);
        $this->assertEquals(15.0, $totals['credit_amount']);
        $this->assertEquals(45.0, $totals['total_amount']);
        $this->assertEquals(45.0, $totals['sales_amount']);
    }
}
