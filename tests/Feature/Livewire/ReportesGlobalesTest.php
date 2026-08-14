<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Reports\ReportesGlobales;
use App\Services\ReportsService;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

/**
 * ReportesGlobalesTest
 *
 * Verifica la pantalla Venta · Reportes Globales (Sprint 3 · Etapa 6):
 * - Modo @stub: renderiza la comparativa con valores cero/unknown y sin HTTP.
 * - Con servicio espía: el x-chart recibe las dos series (periodo actual vs.
 *   año anterior) de ComparisonResponse y la tabla muestra la variación YoY.
 */
class ReportesGlobalesTest extends TestCase
{
    public function test_renders_comparison_in_stub_mode(): void
    {
        config()->set('services.api_web.stubs_enabled', true);

        Livewire::test(ReportesGlobales::class)
            ->assertSee('Reportes Globales')
            ->assertSee('Variación YoY')
            ->assertSee('$ 0.00')
            ->assertSee('wire:poll.30s', false)
            ->assertSee('Sin registros para este período');
    }

    public function test_comparison_series_and_yoy_rows_render_from_service_spy(): void
    {
        $reports = Mockery::mock(ReportsService::class);
        $reports->shouldReceive('salesComparison')->once()->andReturn([
            'success' => true,
            'data' => [
                'current' => [
                    ['period' => '2026-08-14', 'amount' => 1200.0],
                    ['period' => '2026-08-15', 'amount' => 800.0],
                ],
                'previous' => [
                    ['period' => '2026-08-14', 'amount' => 1000.0],
                    ['period' => '2026-08-15', 'amount' => 600.0],
                ],
                'currency' => 'MXN',
                'status' => 'ok',
            ],
        ]);
        $this->app->instance(ReportsService::class, $reports);

        Livewire::test(ReportesGlobales::class)
            ->assertSee('Ventas del periodo')
            ->assertSee('$ 2,000.00')
            ->assertSee('Año anterior')
            ->assertSee('$ 1,600.00')
            ->assertSee('Variación YoY')
            ->assertSee('25%')
            ->assertSee('2026-08-14')
            ->assertSee('2026-08-15')
            ->assertSee('+20.0%')
            ->assertSee('data-rutx-chart', false);
    }

    public function test_limpiar_resets_filters_to_initial_values(): void
    {
        config()->set('services.api_web.stubs_enabled', true);

        Livewire::test(ReportesGlobales::class)
            ->set('range', 'diario')
            ->set('dateFrom', '2026-08-14')
            ->call('limpiar')
            ->assertSet('range', 'mensual')
            ->assertSet('dateFrom', null)
            ->assertSet('zoneId', null);
    }
}
