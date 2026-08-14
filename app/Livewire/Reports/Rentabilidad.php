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
 * Venta · Rentabilidad por Ruta (venta.rentabilidad).
 *
 * Agregados de venta, gasto, entrega y costo disponible por ruta
 * (equivalente funcional a VeMobile, contrato v2 §6.3). Consume
 * ReportsService::routeProfitability (GET /reports/route-profitability).
 *
 * @stub — El endpoint es Prioridad Posterior en el contrato v2 y el
 *         Sincronizador aún no lo publica; el servicio opera en modo stub.
 *         Ver TODO en ReportsService::routeProfitability.
 *
 * La vista solo renderiza componentes; el estado y las llamadas a los
 * servicios viven aquí (guidelines §2.1). Mismo patrón de validación que
 * ReportesGraficas: las reglas de ReportFilterRequest son la fuente única.
 */
class Rentabilidad extends Component
{
    /** Intervalo explícito de polling del tablero (10-30 s según guidelines §2.3). */
    public int $pollInterval = 30;

    public ?string $range = 'mensual';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public ?int $zoneId = null;

    public ?int $routeId = null;

    public function render()
    {
        return view('modules.venta._rentabilidad-content');
    }

    /**
     * Rentabilidad por ruta (data cruda del servicio).
     */
    public function getProfitabilityProperty(): array
    {
        $result = app(ReportsService::class)->routeProfitability($this->filterPayload());

        return $result['success'] ? ($result['data'] ?? []) : [];
    }

    /**
     * KPIs derivados de los totales del contrato (venta, gasto, costo, utilidad).
     */
    public function getKpiProperty(): array
    {
        $totals = $this->profitability['totals'] ?? [];

        return [
            [
                'label' => 'Venta total',
                'value' => (float) ($totals['sales_amount'] ?? 0.0),
                'delta' => null,
                'status' => 'unknown',
            ],
            [
                'label' => 'Gastos',
                'value' => (float) ($totals['expense_amount'] ?? 0.0),
                'delta' => null,
                'status' => 'unknown',
            ],
            [
                'label' => 'Costo disponible',
                'value' => (float) ($totals['cost_amount'] ?? 0.0),
                'delta' => null,
                'status' => 'unknown',
            ],
            [
                'label' => 'Utilidad',
                'value' => (float) ($totals['profit_amount'] ?? 0.0),
                'delta' => null,
                'status' => 'unknown',
            ],
        ];
    }

    /**
     * Serie para <x-chart>: venta vs. gasto por ruta.
     */
    public function getSeriesProperty(): array
    {
        $byRoute = $this->profitability['by_route'] ?? [];

        return [
            'labels' => array_column($byRoute, 'route_name'),
            'datasets' => [
                ['label' => 'Venta', 'data' => array_column($byRoute, 'sales_amount')],
                ['label' => 'Gasto', 'data' => array_column($byRoute, 'expense_amount')],
            ],
        ];
    }

    /**
     * Filas de la tabla: venta, gasto, entrega, costo y utilidad por ruta.
     */
    public function getRowsProperty(): array
    {
        return $this->profitability['by_route'] ?? [];
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
