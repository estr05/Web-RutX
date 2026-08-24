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
        $dashboard->shouldReceive('salesSeries')
            ->once()
            ->andReturn([
                'success' => true,
                'data' => [
                    'series' => [
                        ['period' => '2026-08-14', 'amount' => 15420.50],
                        ['period' => '2026-08-15', 'amount' => 9800.00],
                    ],
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
            ->assertSee('Total período')
            ->assertSee('data-type="line"', false)
            ->assertSee('data-type="bar"', false)
            ->assertSee('2026-08-14', false);
    }

    public function test_route_chart_computes_dual_series_with_tokens(): void
    {
        $dashboard = Mockery::mock(DashboardService::class);
        $dashboard->shouldReceive('summary')->andReturn(['success' => true, 'data' => ['kpi' => []]]);
        $dashboard->shouldReceive('salesSeries')->once()->andReturn(['success' => false]);
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
        $dashboard->shouldReceive('salesSeries')->once()->andReturn(VisualDashboardFixture::series());
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
            ->assertSee('Total período')
            ->assertSee('data-type="line"', false)
            ->assertSee('2026-08-21', false);
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
        $dashboard->shouldReceive('salesSeries')->once()->andReturn(['success' => false]);
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

    public function test_api_error_banner_is_visible_when_report_fails(): void
    {
        $dashboard = Mockery::mock(DashboardService::class);
        $dashboard->shouldReceive('summary')->andReturn(['success' => true, 'data' => ['kpi' => []]]);
        $dashboard->shouldReceive('salesSeries')->once()->andReturn([
            'success' => true,
            'data' => ['series' => [['period' => '2026-08-14', 'amount' => 100.0]]],
        ]);
        $this->app->instance(DashboardService::class, $dashboard);

        $reports = Mockery::mock(ReportsService::class);
        $reports->shouldReceive('report')->once()->andReturn([
            'success' => false,
            'code' => 'API_UNAVAILABLE',
            'message' => 'No se pudo conectar con el servicio.',
            'errors' => null,
            'trace_id' => null,
        ]);
        $this->app->instance(ReportsService::class, $reports);

        Livewire::test(ReportesGraficas::class)
            ->assertSee('El reporte por ruta no está disponible ahora', false)
            ->assertSee('API_UNAVAILABLE', false)
            ->assertSee('No se pudo conectar con el servicio.', false)
            // El trace_id nunca llega a la pantalla.
            ->assertDontSee('trace_id')
            ->assertSee('Sin registros para este período');
    }

    public function test_invalid_envelope_from_reports_is_treated_as_error(): void
    {
        $dashboard = Mockery::mock(DashboardService::class);
        $dashboard->shouldReceive('summary')->andReturn(['success' => true, 'data' => ['kpi' => []]]);
        $dashboard->shouldReceive('salesSeries')->once()->andReturn([
            'success' => true,
            'data' => ['series' => [['period' => '2026-08-14', 'amount' => 100.0]]],
        ]);
        $this->app->instance(DashboardService::class, $dashboard);

        $reports = Mockery::mock(ReportsService::class);
        $reports->shouldReceive('report')->once()->andReturn([
            'success' => false,
            'code' => 'INVALID_ENVELOPE',
            'message' => 'El servicio respondió con una estructura inesperada.',
            'errors' => null,
            'trace_id' => null,
        ]);
        $this->app->instance(ReportsService::class, $reports);

        Livewire::test(ReportesGraficas::class)
            ->assertSee('El servicio respondió con una estructura inesperada.', false)
            ->assertDontSee('Total período');
    }

    public function test_partial_dates_show_warning_and_skip_all_http_calls(): void
    {
        // Las expectativas 'once()' se consumen ÚNICAMENTE con los filtros válidos
        // del mount inicial. Al asignar filtros inválidos no debe haber llamadas extra.
        $dashboard = Mockery::mock(DashboardService::class);
        $dashboard->shouldReceive('summary')->once()->andReturn(['success' => true, 'data' => ['kpi' => []]]);
        $dashboard->shouldReceive('salesSeries')->once()->andReturn(['success' => false]);
        $this->app->instance(DashboardService::class, $dashboard);

        $reports = Mockery::mock(ReportsService::class);
        $reports->shouldReceive('report')->once()->andReturn(['success' => true, 'data' => []]);
        $this->app->instance(ReportsService::class, $reports);

        Livewire::test(ReportesGraficas::class)
            // Asignar solo dateFrom (estado inválido) sin llamar a consultar()
            ->set('dateFrom', '2026-08-14')
            ->assertSee('filtros incompletos o inválidos', false)
            ->assertDontSee('Total período');
    }

    public function test_only_date_to_shows_warning_without_consultar(): void
    {
        $dashboard = Mockery::mock(DashboardService::class);
        $dashboard->shouldReceive('summary')->once()->andReturn(['success' => true, 'data' => ['kpi' => []]]);
        $dashboard->shouldReceive('salesSeries')->once()->andReturn(['success' => false]);
        $this->app->instance(DashboardService::class, $dashboard);

        $reports = Mockery::mock(ReportsService::class);
        $reports->shouldReceive('report')->once()->andReturn(['success' => true, 'data' => []]);
        $this->app->instance(ReportsService::class, $reports);

        Livewire::test(ReportesGraficas::class)
            ->set('dateTo', '2026-08-14')
            ->assertSee('filtros incompletos o inválidos', false);
    }

    public function test_inverted_dates_fail_validation_without_extra_calls(): void
    {
        $dashboard = Mockery::mock(DashboardService::class);
        $dashboard->shouldReceive('summary')->once()->andReturn(['success' => true, 'data' => ['kpi' => []]]);
        $dashboard->shouldReceive('salesSeries')->once()->andReturn(['success' => false]);
        $this->app->instance(DashboardService::class, $dashboard);

        $reports = Mockery::mock(ReportsService::class);
        $reports->shouldReceive('report')->once()->andReturn(['success' => true, 'data' => []]);
        $this->app->instance(ReportsService::class, $reports);

        Livewire::test(ReportesGraficas::class)
            ->set('dateFrom', '2026-08-20')
            ->set('dateTo', '2026-08-10')
            ->assertSee('filtros incompletos o inválidos', false)
            ->call('consultar') // Forzamos consulta explícita para evaluar el ErrorBag
            ->assertHasErrors(['date_to'])
            ->assertSee('filtros incompletos o inválidos', false);
    }

    public function test_invalid_date_format_shows_warning(): void
    {
        config()->set('services.api_web.stubs_enabled', true);

        Livewire::test(ReportesGraficas::class)
            ->set('dateFrom', '2026/08/10')
            ->assertSee('filtros incompletos o inválidos', false)
            ->call('consultar')
            ->assertHasErrors(['date_from']);
    }

    public function test_correcting_filters_removes_warning_and_resumes_calls(): void
    {
        // En este test, los servicios se llamarán en el mount inicial (1) y luego
        // otra vez cuando los filtros vuelvan a ser válidos en conjunto (2).
        $dashboard = Mockery::mock(DashboardService::class);
        $dashboard->shouldReceive('summary')->twice()->andReturn(['success' => true, 'data' => ['kpi' => []]]);
        $dashboard->shouldReceive('salesSeries')->twice()->andReturn(['success' => false]);
        $this->app->instance(DashboardService::class, $dashboard);

        $reports = Mockery::mock(ReportsService::class);
        $reports->shouldReceive('report')->twice()->andReturn(['success' => true, 'data' => []]);
        $this->app->instance(ReportsService::class, $reports);

        Livewire::test(ReportesGraficas::class)
            // Estado válido inicial -> consume la 1ra llamada
            ->assertDontSee('filtros incompletos o inválidos', false)
            
            // Estado inválido (solo dateFrom) -> NO debe consumir llamadas
            ->set('dateFrom', '2026-08-10')
            ->assertSee('filtros incompletos o inválidos', false)
            
            // Estado válido (rango completo) -> consume la 2da llamada
            ->set('dateTo', '2026-08-16')
            ->assertDontSee('filtros incompletos o inválidos', false);
    }

    public function test_valid_date_range_passes_validation_and_queries(): void
    {
        config()->set('services.api_web.stubs_enabled', true);

        Livewire::test(ReportesGraficas::class)
            ->set('range', 'semanal')
            ->set('dateFrom', '2026-08-10')
            ->set('dateTo', '2026-08-16')
            ->call('consultar')
            ->assertHasNoErrors()
            ->assertDontSee('filtros incompletos o inválidos', false);
    }

    /**
     * Fixture de 25 rutas para ejercitar la paginación server-side.
     *
     * @return array<string, mixed>
     */
    private function paginatedReport(): array
    {
        $byRoute = [];

        for ($i = 1; $i <= 25; $i++) {
            $byRoute[] = [
                'route_name' => sprintf('Ruta %02d', $i),
                'pieces' => $i,
                'cash_amount' => 100.0 * $i,
                'credit_amount' => 50.0 * $i,
                'total_amount' => 150.0 * $i,
            ];
        }

        return [
            'success' => true,
            'data' => [
                'totals' => ['sales_amount' => 48750.0, 'pieces' => 325, 'currency' => 'MXN'],
                'by_route' => $byRoute,
                'status' => 'ok',
            ],
        ];
    }

    private function fakeServicesForPagination(): void
    {
        $dashboard = Mockery::mock(DashboardService::class);
        $dashboard->shouldReceive('summary')->andReturn(['success' => true, 'data' => ['kpi' => []]]);
        $dashboard->shouldReceive('salesSeries')->andReturn(['success' => false]);
        $this->app->instance(DashboardService::class, $dashboard);

        $reports = Mockery::mock(ReportsService::class);
        $reports->shouldReceive('report')->andReturn($this->paginatedReport());
        $this->app->instance(ReportsService::class, $reports);
    }

    public function test_pagination_slices_rows_and_navigates(): void
    {
        $this->fakeServicesForPagination();

        // Nota: la gráfica de barras usa la colección completa, así que los
        // nombres "Ruta NN" aparecen siempre en el HTML; la página activa se
        // verifica con los montos formateados de las filas de la tabla.
        $component = Livewire::test(ReportesGraficas::class)
            ->assertSet('page', 1)
            ->assertSee('Página 1 de 3', false)
            ->assertSee('$ 150.00', false)
            ->assertDontSee('$ 1,650.00', false);

        // Barra y totales usan la colección completa, no la página actual.
        $this->assertCount(25, $component->instance()->routeChart['labels']);
        $this->assertSame(325, $component->instance()->totals['pieces']);

        $component->call('nextPage')
            ->assertSet('page', 2)
            ->assertSee('Página 2 de 3', false)
            ->assertSee('$ 1,650.00', false)
            ->assertDontSee('$ 150.00', false);

        $component->call('prevPage')->assertSet('page', 1);
        $component->call('gotoPage', 99)->assertSet('page', 3)
            ->assertSee('Página 3 de 3', false)
            ->assertSee('$ 3,150.00', false)
            ->assertDontSee('Ruta 31');
        $component->call('gotoPage', 0)->assertSet('page', 1);
    }

    public function test_filter_change_resets_pagination_to_first_page(): void
    {
        $this->fakeServicesForPagination();

        Livewire::test(ReportesGraficas::class)
            ->call('nextPage')
            ->assertSet('page', 2)
            ->set('zoneId', 2)
            ->assertSet('page', 1)
            ->call('nextPage')
            ->set('routeId', 7)
            ->assertSet('page', 1)
            ->call('nextPage')
            ->call('limpiar')
            ->assertSet('page', 1);
    }
}
