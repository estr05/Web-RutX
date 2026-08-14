<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Http\Requests\ReportFilterRequest;
use App\Services\DashboardService;
use App\Services\ReportsService;
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
     * KPIs del tablero (DashboardSummaryResponse → 7 tarjetas).
     */
    public function getKpiProperty(): array
    {
        $result = app(DashboardService::class)->summary($this->filterPayload());

        return $result['success'] ? ($result['data']['kpi'] ?? []) : [];
    }

    /**
     * Serie de ventas adaptada al contrato de <x-chart> (labels + datasets).
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
     * Movimientos por ruta (SalesReportResponse.by_route → tabla).
     */
    public function getMovimientosProperty(): array
    {
        $result = app(ReportsService::class)->report($this->filterPayload());

        return $result['success'] ? ($result['data']['by_route'] ?? []) : [];
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
