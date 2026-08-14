<?php

declare(strict_types=1);

namespace App\Livewire\Routes;

use App\Http\Requests\ReportFilterRequest;
use App\Services\RouteMonitorService;
use App\Support\Feedback;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Ruta · Jornada del Día (ruta.jornada).
 *
 * Línea temporal de la ruta seleccionada: hora, cliente, tipo de visita
 * (atendida / no venta), duración y monto. Consume
 * RouteMonitorService::routeDetail (GET /route-monitor/{id} →
 * RouteTimelineResponse) y ::routes (GET /routes → catálogo para el selector).
 *
 * La vista solo renderiza componentes; el estado, las llamadas a los
 * servicios y las transformaciones viven aquí (guidelines §2.1).
 */
class JornadaDelDia extends Component
{
    /** Polling del monitoreo en vivo: 10-30 s (guidelines §2.3); 20 s de compromiso. */
    public int $pollInterval = 20;

    public ?int $routeId = null;

    public function render()
    {
        return view('modules.ruta._jornada-content');
    }

    /**
     * Catálogo de rutas (GET /routes) para el selector de la jornada.
     */
    public function getRoutesProperty(): array
    {
        $result = app(RouteMonitorService::class)->routes($this->filterPayload());

        return $result['success'] ? ($result['data'] ?? []) : [];
    }

    /**
     * RouteTimelineResponse del detalle de la ruta seleccionada.
     */
    public function getDetailProperty(): array
    {
        if ($this->routeId === null) {
            return [];
        }

        $result = app(RouteMonitorService::class)->routeDetail($this->routeId);

        return $result['success'] ? ($result['data'] ?? []) : [];
    }

    /**
     * Filas de la tabla: hora, cliente, tipo con estado y duración/monto.
     */
    public function getRowsProperty(): array
    {
        return array_values(array_map(
            fn (array $event): array => [
                'at' => $event['at'] ?? null,
                'customer_name' => $event['customer_name'] ?? 'Cliente sin nombre',
                'type' => $event['type'] ?? 'no-sale',
                'type_label' => ($event['type'] ?? 'no-sale') === 'visit' ? 'Atendida' : 'No venta',
                'duration_minutes' => $event['duration_minutes'] ?? null,
                'amount' => $event['amount'] ?? null,
            ],
            $this->detail['timeline'] ?? [],
        ));
    }

    /**
     * Dispara la consulta desde el formulario de filtros: valida el estado
     * actual con las reglas de ReportFilterRequest antes de refrescar.
     */
    public function consultar()
    {
        $validator = Validator::make($this->filterInput(), (new ReportFilterRequest)->rules());

        if ($validator->fails()) {
            $this->dispatch('rutx:feedback', Feedback::error('Revisa los filtros de la jornada.'));

            return;
        }
    }

    /**
     * Filtros del componente en snake_case (forma del contrato v2).
     *
     * @return array<string, mixed>
     */
    private function filterInput(): array
    {
        return [
            'range' => null,
            'date_from' => null,
            'date_to' => null,
            'zone_id' => null,
            'route_id' => $this->routeId,
        ];
    }

    /**
     * Payload hacia la API: solo campos validados con las reglas de
     * ReportFilterRequest y con valor (guidelines §1.4).
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
