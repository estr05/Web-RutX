<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Http\Requests\ReportFilterRequest;
use App\Services\ReportsService;
use App\Support\Feedback;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Venta · Reportes Globales (venta.globales).
 *
 * Comparativa del periodo seleccionado contra el mismo periodo del año
 * anterior (YoY, Plan de Ejecución §5 Fase 2). Consume
 * ReportsService::salesComparison (GET /reports/sales-comparison →
 * ComparisonResponse: dos series current/previous).
 *
 * La vista solo renderiza componentes; el estado y las llamadas a los
 * servicios viven aquí (guidelines §2.1). Mismo patrón de validación que
 * ReportesGraficas: las reglas de ReportFilterRequest son la fuente única.
 */
class ReportesGlobales extends Component
{
    /** Intervalo explícito de polling del tablero (10-30 s según guidelines §2.3). */
    public int $pollInterval = 30;

    public ?string $range = 'mensual';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public ?int $zoneId = null;

    public function render()
    {
        return view('modules.venta._reportes-globales-content');
    }

    /**
     * Comparativa YoY (ComparisonResponse: series current/previous).
     */
    public function getComparisonProperty(): array
    {
        $result = app(ReportsService::class)->salesComparison($this->filterPayload());

        return $result['success'] ? ($result['data'] ?? []) : [];
    }

    /**
     * KPIs derivados de la comparativa: total actual, total año anterior y
     * variación YoY. Los cálculos viven aquí, nunca en la vista (guidelines §2.1).
     */
    public function getKpiProperty(): array
    {
        $current = array_sum(array_column($this->comparison['current'] ?? [], 'amount'));
        $previous = array_sum(array_column($this->comparison['previous'] ?? [], 'amount'));
        $yoy = $previous > 0.0 ? (($current - $previous) / $previous) * 100 : null;

        return [
            [
                'label' => 'Ventas del periodo',
                'value' => (float) $current,
                'delta' => null,
                'status' => 'unknown',
            ],
            [
                'label' => 'Año anterior',
                'value' => (float) $previous,
                'delta' => null,
                'status' => 'unknown',
            ],
            [
                'label' => 'Variación YoY',
                'value' => (float) ($current - $previous),
                'delta' => $yoy === null ? null : round($yoy, 1).'%',
                'status' => $yoy === null ? 'unknown' : ($yoy >= 0 ? 'success' : 'error'),
            ],
        ];
    }

    /**
     * Serie para <x-chart>: dos datasets (periodo actual vs. año anterior).
     */
    public function getSeriesProperty(): array
    {
        $comparison = $this->comparison;

        $current = $comparison['current'] ?? [];
        $previous = $comparison['previous'] ?? [];

        $periods = array_values(array_unique(array_merge(
            array_column($current, 'period'),
            array_column($previous, 'period'),
        )));

        return [
            'labels' => $periods,
            'datasets' => [
                ['label' => 'Periodo actual', 'data' => $this->amountsByPeriod($current, $periods)],
                ['label' => 'Año anterior', 'data' => $this->amountsByPeriod($previous, $periods)],
            ],
        ];
    }

    /**
     * Filas de la tabla comparativa: periodo, actual, anterior y variación YoY.
     */
    public function getRowsProperty(): array
    {
        $comparison = $this->comparison;

        $current = array_column($comparison['current'] ?? [], 'amount', 'period');
        $previous = array_column($comparison['previous'] ?? [], 'amount', 'period');

        $rows = [];
        foreach (array_unique(array_merge(array_keys($current), array_keys($previous))) as $period) {
            $now = (float) ($current[$period] ?? 0.0);
            $before = (float) ($previous[$period] ?? 0.0);

            $rows[] = [
                'period' => $period,
                'current_amount' => $now,
                'previous_amount' => $before,
                'yoy_delta' => $before > 0.0 ? (($now - $before) / $before) * 100 : null,
            ];
        }

        return $rows;
    }

    /**
     * Dispara la consulta desde el formulario de filtros: valida el estado
     * actual con las reglas de ReportFilterRequest antes de refrescar.
     */
    public function consultar()
    {
        $validator = Validator::make($this->filterInput(), (new ReportFilterRequest)->rules());

        if ($validator->fails()) {
            $this->dispatch('rutx:feedback', Feedback::error('Revisa los filtros del reporte.'));
            $this->resetErrorBag($validator->errors()->getMessages());

            return;
        }

        $this->resetErrorBag();
    }

    /**
     * Restablece los filtros a su valor inicial.
     */
    public function limpiar()
    {
        $this->reset(['range', 'dateFrom', 'dateTo', 'zoneId']);
    }

    /**
     * Montos por periodo alineados al array de labels del gráfico.
     */
    private function amountsByPeriod(array $points, array $periods): array
    {
        $byPeriod = array_column($points, 'amount', 'period');

        return array_map(fn (string $period): float => (float) ($byPeriod[$period] ?? 0.0), $periods);
    }

    /**
     * Filtros del componente en snake_case (forma del contrato v2).
     *
     * @return array<string, mixed>
     */
    private function filterInput(): array
    {
        return [
            'range' => $this->range,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'zone_id' => $this->zoneId,
        ];
    }

    /**
     * Payload hacia la API: solo campos validados con las reglas de
     * ReportFilterRequest y con valor (guidelines §1.4). Un estado inválido
     * devuelve filtros vacíos (cero/unknown en modo stub).
     *
     * @return array<string, mixed>
     */
    private function filterPayload(): array
    {
        try {
            $validated = Validator::make($this->filterInput(), (new ReportFilterRequest)->rules())->validated();
        } catch (ValidationException) {
            return [];
        }

        return collect($validated)
            ->reject(fn (mixed $value): bool => is_null($value) || $value === '')
            ->all();
    }
}
