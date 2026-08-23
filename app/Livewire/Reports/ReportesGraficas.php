<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Http\Requests\ReportFilterRequest;
use App\Services\DashboardService;
use App\Services\ReportsService;
use App\Support\DashboardKpiCatalog;
use App\Support\Feedback;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
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

    /** True cuando la última llamada a cualquier servicio falló. La vista usa esto para emptyMessage descriptivos. */
    public bool $hasApiError = false;

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
        $result = app(DashboardService::class)->summary($this->filterPayload());

        if (! $result['success']) {
            $this->hasApiError = true;

            return ['success' => false, 'data' => []];
        }

        $this->hasApiError = false;

        return $result;
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
        $result = app(ReportsService::class)->report($this->filterPayload());

        if (! $result['success']) {
            $this->hasApiError = true;

            return ['success' => false, 'data' => []];
        }

        return $result;
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
                    'colorToken' => '--rutx-chart-orange',
                ],
            ],
        ];
    }

    /**
     * Serie temporal de ventas para comparativas futuras (<x-chart>).
     */
    public function getSeriesProperty(): array
    {
        $result = app(DashboardService::class)->salesSeries($this->seriesPayload());

        if (! $result['success']) {
            $this->hasApiError = true;

            return ['labels' => [], 'datasets' => []];
        }

        $series = $result['data']['series'] ?? [];
        $periods = array_column($series, 'period');

        return [
            'labels' => $this->compactLabels($periods),
            'datasets' => [
                ['label' => 'Venta', 'data' => array_column($series, 'amount')],
            ],
        ];
    }

    /**
     * Payload para /dashboard/sales-series: filtros validados + ventana
     * coherente con ResolverVentanaResumen del Sincronizador.
     */
    private function seriesPayload(): array
    {
        $payload = $this->filterPayload();

        if (blank($payload['date_from'] ?? null) && blank($payload['date_to'] ?? null)) {
            $hoy = now()->toDateString();

            match ($payload['range'] ?? null) {
                'diario' => $payload += ['date_from' => $hoy, 'date_to' => $hoy],
                'semanal' => $payload += [
                    'date_from' => now()->startOfWeek(Carbon::MONDAY)->toDateString(),
                    'date_to' => $hoy,
                ],
                default => null,
            };
        }

        return $payload;
    }

    /**
     * Etiquetas compactas para el eje X según la forma del periodo.
     *
     * @param  array<int, string>  $periods
     * @return array<int, string>
     */
    private function compactLabels(array $periods): array
    {
        if ($periods === []) {
            return [];
        }

        $mismoDia = count(array_unique(array_map(
            fn (string $p): string => substr($p, 0, 10),
            $periods,
        ))) === 1;

        return array_map(function (string $periodo) use ($mismoDia): string {
            if ($mismoDia && preg_match('/^\d{4}-\d{2}-\d{2} (\d{2}:\d{2})$/', $periodo, $m)) {
                return $m[1];
            }
            if (preg_match('/^\d{4}-(\d{2})-(\d{2})$/', $periodo, $m)) {
                return (string) ((int) $m[2]).'/'.(string) ((int) $m[1]);
            }
            return $periodo;
        }, $periods);
    }

    /**
     * Determina si la gráfica de serie debe activar el scroll horizontal.
     *
     * Regla: se activa en "mensual" (12 puntos > 6 visibles) o cuando el
     * usuario usa un rango explícito Desde/Hasta que puede superar los 6 puntos.
     * La vista NO debe hacer este cálculo — vive aquí para que sea testeable
     * y no viole la separación de responsabilidades (guidelines §2.1).
     */
    public function getIsSeriesScrollableProperty(): bool
    {
        return $this->range === 'mensual'
            || ($this->dateFrom !== null && $this->dateTo !== null);
    }

    /**
     * Dispara la consulta desde el formulario de filtros: valida el estado
     * actual con las reglas de ReportFilterRequest antes de refrescar.
     */
    #[On('rutx.reports.retry')]
    public function consultar()
    {
        $validator = Validator::make($this->filterInput(), (new ReportFilterRequest)->rules());

        if ($validator->fails()) {
            $this->dispatch('rutx:feedback', Feedback::error('Revisa los filtros del reporte.'));
            $this->setErrorBag($validator->errors());

            return;
        }

        $this->hasApiError = false;
        $this->resetErrorBag();
        
        // Disparar toast de error si la API sigue inalcanzable tras consultar manualmente
        $result = app(DashboardService::class)->summary($this->filterPayload());
        if (!$result['success']) {
            $this->handleApiError($result);
            return;
        }
        
        $resultSeries = app(DashboardService::class)->salesSeries($this->seriesPayload());
        if (!$resultSeries['success']) {
            $this->handleApiError($resultSeries);
            return;
        }
        
        $resultReports = app(ReportsService::class)->report($this->filterPayload());
        if (!$resultReports['success']) {
            $this->handleApiError($resultReports);
        }
    }

    /**
     * Fuente única para despachar feedback cuando la API falla.
     */
    private function handleApiError(array $result): void
    {
        $this->hasApiError = true;

        $code = $result['code'] ?? 'UNKNOWN';

        $message = match ($code) {
            'UNAUTHORIZED' => 'Tu sesión expiró. Recarga la página para continuar.',
            'API_UNAVAILABLE' => 'No se pudo conectar con el servidor. Verifica la conexión.',
            'FEATURE_NOT_READY' => 'Este reporte aún no está disponible en tu versión actual.',
            'GATEWAY_TIMEOUT' => 'El servidor tardó demasiado. Intenta con un período más corto.',
            default => $result['message'] ?? 'Ocurrió un error al obtener los datos.',
        };

        $isRecoverable = in_array($code, ['API_UNAVAILABLE', 'GATEWAY_TIMEOUT'], true);

        $this->dispatch('rutx:feedback', Feedback::error(
            message: $message,
            isRecoverable: $isRecoverable,
            retryEvent: $isRecoverable ? 'rutx.reports.retry' : null,
        ));
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
