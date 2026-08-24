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

    /** Página actual de la tabla de movimientos (paginación server-side). */
    public int $page = 1;

    /** Filas por página de la tabla de movimientos. */
    public int $perPage = 10;

    /**
     * Propiedad computada pura que evalúa si los filtros actuales son inválidos
     * (por ejemplo, fechas parciales o invertidas).
     */
    public function getHasInvalidFiltersProperty(): bool
    {
        return Validator::make($this->filterInput(), (new ReportFilterRequest)->rules())->fails();
    }

    public function render()
    {
        // Paginación calculada en render (NO como propiedad computada: su
        // cache sobreviviría a cambios de $page dentro del mismo ciclo).
        // Clamp defensivo: un cambio de filtros puede reducir el conjunto
        // de filas y dejar la página fuera de rango.
        $movimientos = $this->movimientos;
        $pageCount = max(1, (int) ceil(count($movimientos) / $this->perPage));

        if ($this->page > $pageCount) {
            $this->page = $pageCount;
        }

        $pagina = array_slice($movimientos, ($this->page - 1) * $this->perPage, $this->perPage);

        return view('modules.venta._reportes-graficas-content', [
            'paginatedMovimientos' => $pagina,
            'pageCount' => $pageCount,
        ]);
    }

    /**
     * Reinicia la paginación cuando cambia cualquier filtro; así la tabla
     * nunca queda en una página huérfana tras cambiar el conjunto de filas.
     */
    public function updated(string $name): void
    {
        if (in_array($name, ['range', 'dateFrom', 'dateTo', 'zoneId', 'routeId'], true)) {
            $this->page = 1;
        }
    }

    /**
     * Respuesta cruda consolidada de DashboardService (summary).
     * Evita llamadas HTTP duplicadas por render (guidelines §2.1).
     */
    public function getDashboardProperty(): array
    {
        $payload = $this->filterPayload();

        if ($this->hasInvalidFilters) {
            return $this->invalidFiltersEnvelope();
        }

        return app(DashboardService::class)->summary($payload);
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
        $payload = $this->filterPayload();

        if ($this->hasInvalidFilters) {
            return $this->invalidFiltersEnvelope();
        }

        return app(ReportsService::class)->report($payload);
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
     * Envelope crudo de la serie de ventas (DashboardService::salesSeries).
     * apiError necesita el envelope original con 'success'; la forma para
     * <x-chart> ($series) se transforma a partir de este.
     */
    public function getSeriesResultProperty(): array
    {
        $payload = $this->filterPayload();

        if ($this->hasInvalidFilters) {
            return $this->invalidFiltersEnvelope();
        }

        return app(DashboardService::class)->salesSeries($payload);
    }

    /**
     * Serie temporal de ventas para <x-chart type="line">.
     */
    public function getSeriesProperty(): array
    {
        $result = $this->seriesResult;

        if (! ($result['success'] ?? false)) {
            return ['labels' => [], 'datasets' => []];
        }

        $series = $result['data']['series'] ?? [];

        return [
            'labels' => array_column($series, 'period'),
            'datasets' => [
                ['label' => 'Venta', 'data' => array_column($series, 'amount'), 'colorToken' => '--rutx-chart-blue'],
            ],
        ];
    }

    /**
     * Primer error real de la API entre los tres servicios de la pantalla.
     * Null cuando todo tuvo éxito. Mensaje funcional; el trace_id se queda
     * en los logs (nunca en pantalla).
     */
    public function getApiErrorProperty(): ?array
    {
        foreach ([
            'dashboard' => 'El tablero',
            'seriesResult' => 'La serie de ventas',
            'reportData' => 'El reporte por ruta',
        ] as $source => $label) {
            $result = $this->{$source};

            if (! ($result['success'] ?? false)) {
                return [
                    'source' => $label,
                    'code' => is_string($result['code'] ?? null) ? $result['code'] : 'ERROR',
                    'message' => is_string($result['message'] ?? null)
                        ? $result['message']
                        : __('No fue posible consultar el servicio.'),
                ];
            }
        }

        return null;
    }

    /**
     * Huella canónica de los filtros activos para wire:key: la tabla solo
     * se re-crea cuando cambian filtros o el conjunto de filas, nunca en
     * cada ciclo de polling con datos idénticos.
     */
    public function getFilterKeyProperty(): string
    {
        return md5((string) json_encode($this->filterPayload()));
    }

    /**
     * Envelope de fallo local por filtros inválidos: evita cualquier llamada
     * HTTP con un payload parcial; la vista muestra el aviso correspondiente.
     *
     * @return array<string, mixed>
     */
    private function invalidFiltersEnvelope(): array
    {
        return [
            'success' => false,
            'code' => 'INVALID_FILTERS',
            'message' => __('Corrige los filtros para consultar datos.'),
            'errors' => null,
            'trace_id' => null,
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

        $this->page = 1;
        $this->resetErrorBag();
    }

    /**
     * Restablece los filtros a su valor inicial (y la paginación).
     */
    public function limpiar()
    {
        $this->reset(['range', 'dateFrom', 'dateTo', 'zoneId', 'routeId']);
        $this->resetErrorBag();
        $this->page = 1;
    }

    /**
     * Navegación de paginación de la tabla, siempre acotada a [1, pageCount].
     * El conteo se calcula inline (NO como propiedad computada: su cache
     * sobreviviría a cambios de $page dentro del mismo ciclo).
     */
    public function gotoPage(int $page): void
    {
        $pageCount = max(1, (int) ceil(count($this->movimientos) / $this->perPage));
        $this->page = max(1, min($page, $pageCount));
    }

    public function nextPage(): void
    {
        $this->gotoPage($this->page + 1);
    }

    public function prevPage(): void
    {
        $this->gotoPage($this->page - 1);
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
        if ($this->hasInvalidFilters) {
            return [];
        }

        $validated = Validator::make($this->filterInput(), (new ReportFilterRequest)->rules())->validated();

        return collect($validated)
            ->reject(fn (mixed $value): bool => is_null($value) || $value === '')
            ->all();
    }
}
