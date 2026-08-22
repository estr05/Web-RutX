<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Http\Requests\ReportFilterRequest;
use App\Services\DashboardService;
use App\Services\ReportsService;
use App\Support\DashboardKpiCatalog;
use App\Support\Feedback;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Venta · Reportes y Gráficas (venta.reportes).
 *
 * Primera pantalla real del Sprint 3: KPIs del dashboard, serie de ventas y
 * tabla de movimientos. La vista solo renderiza componentes; el estado y las
 * llamadas a los servicios viven aquí (guidelines §2.1).
 *
 * Los filtros del componente se validan con las reglas de ReportFilterRequest
 * (fuente única); el payload hacia la API solo lleva campos validados con
 * valor (guidelines §1.4). En Livewire request() no expone las props del
 * componente, por eso se construye un array con ellas y se valida directo.
 */
class ReportesGraficas extends Component
{
    /** Intervalo explícito de polling del tablero (10-30 s según guidelines §2.3). */
    public int $pollInterval = 30;

    public ?string $range = 'diario';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public ?int $zoneId = null;

    public ?int $routeId = null;

    public function render()
    {
        return view('modules.venta._reportes-graficas-content');
    }

    /**
     * Respuesta cruda consolidada de DashboardService (summary).
     * Evita llamadas HTTP duplicadas por render (guidelines §2.1).
     */
    public function getDashboardProperty(): array
    {
        return app(DashboardService::class)->summary($this->filterPayload());
    }

    /**
     * Divisa contractual del dashboard (ej. 'MXN').
     */
    public function getCurrencyProperty(): string
    {
        $dashboard = $this->dashboard;

        return $dashboard['success'] ? ($dashboard['data']['meta']['currency'] ?? '') : '';
    }

    /**
     * KPIs del tablero enriquecidos con el catálogo visual (DashboardSummaryResponse → 7 tarjetas).
     */
    public function getKpiProperty(): array
    {
        $dashboard = $this->dashboard;

        if (! $dashboard['success']) {
            return [];
        }

        $rawKpis = $dashboard['data']['kpi'] ?? [];

        return array_map(function (array $kpi): array {
            $label = $kpi['label'] ?? '';
            $meta = DashboardKpiCatalog::get($label);

            return array_merge($kpi, [
                'format' => $meta['format'],
                'iconName' => $meta['iconName'],
                'group' => $meta['group'],
            ]);
        }, $rawKpis);
    }

    /**
     * Respuesta cruda consolidada de ReportsService (report).
     * Evita llamadas HTTP duplicadas al construir tabla, totales y routeChart.
     */
    public function getReportDataProperty(): array
    {
        return app(ReportsService::class)->report($this->filterPayload());
    }

    /**
     * Movimientos por ruta (SalesReportResponse.by_route → tabla).
     */
    public function getMovimientosProperty(): array
    {
        $result = $this->reportData;

        return $result['success'] ? ($result['data']['by_route'] ?? []) : [];
    }

    /**
     * Totales consolidados del reporte (SalesReportResponse.totals → footer tabla).
     */
    public function getTotalsProperty(): array
    {
        $result = $this->reportData;

        if (! $result['success']) {
            return [];
        }

        $totals = $result['data']['totals'] ?? [];
        if (! empty($totals)) {
            return $totals;
        }

        $movimientos = $this->movimientos;

        return [
            'pieces' => array_sum(array_column($movimientos, 'pieces')),
            'cash_amount' => array_sum(array_column($movimientos, 'cash_amount')),
            'credit_amount' => array_sum(array_column($movimientos, 'credit_amount')),
            'total_amount' => array_sum(array_column($movimientos, 'total_amount')),
            'sales_amount' => array_sum(array_column($movimientos, 'total_amount')),
        ];
    }

    /**
     * Gráfica de barras comparativa Contado vs Crédito por ruta (ReportsService::report()['by_route']).
     */
    public function getRouteChartProperty(): array
    {
        $byRoute = $this->movimientos;

        if (empty($byRoute)) {
            return ['labels' => [], 'datasets' => []];
        }

        return [
            'labels' => array_column($byRoute, 'route_name'),
            'datasets' => [
                [
                    'label' => 'Contado',
                    'data' => array_map(fn (array $r): float => (float) ($r['cash_amount'] ?? 0.0), $byRoute),
                    'colorToken' => '--rutx-chart-blue',
                ],
                [
                    'label' => 'Crédito',
                    'data' => array_map(fn (array $r): float => (float) ($r['credit_amount'] ?? 0.0), $byRoute),
                    'colorToken' => '--rutx-chart-cyan',
                ],
            ],
        ];
    }

    /**
     * Serie temporal de ventas para comparativas futuras (<x-chart>).
     */
    public function getSeriesProperty(): array
    {
        $result = app(DashboardService::class)->salesSeries($this->filterPayload());

        if (! $result['success']) {
            return ['labels' => [], 'datasets' => []];
        }

        $series = $result['data']['series'] ?? [];

        return [
            'labels' => array_column($series, 'period'),
            'datasets' => [
                ['label' => 'Venta', 'data' => array_column($series, 'amount')],
            ],
        ];
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
            $this->setErrorBag($validator->errors());

            return;
        }

        $this->resetErrorBag();
    }

    /**
     * Restablece los filtros a su valor inicial.
     */
    public function limpiar()
    {
        $this->reset(['range', 'dateFrom', 'dateTo', 'zoneId', 'routeId']);
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
            'route_id' => $this->routeId,
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
