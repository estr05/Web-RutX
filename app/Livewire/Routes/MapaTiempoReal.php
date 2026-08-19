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
 * Ruta · Mapa en Tiempo Real (ruta.mapa).
 *
 * El mapa rompe la anatomía estándar (Plan Visual §2.4): el mapa Leaflet
 * ocupa el papel principal y los KPIs de ruta van en un panel lateral
 * colapsable (prop $sidePanelOpen). Consume RouteMonitorService::monitor
 * (GET /route-monitor → RouteMonitorListResponse) y ::routes (GET /routes
 * → catálogo para el filtro cascada zona → ruta).
 *
 * Marcadores y KPIs son propiedades computadas: la vista solo renderiza
 * componentes y data-attributes (guidelines §2.1).
 */
class MapaTiempoReal extends Component
{
    /** Polling del monitoreo en vivo: 10-30 s (guidelines §2.3); 20 s de compromiso. */
    public int $pollInterval = 20;

    public ?int $zoneId = null;

    public ?int $routeId = null;

    public bool $sidePanelOpen = true;

    public function render()
    {
        return view('modules.ruta._mapa-content');
    }

    /**
     * Catálogo de rutas (GET /routes) para el filtro cascada zona → ruta.
     */
    public function getRoutesProperty(): array
    {
        $result = app(RouteMonitorService::class)->routes($this->filterPayload());

        return $result['success'] ? ($result['data'] ?? []) : [];
    }

    /**
     * Zonas únicas derivadas del catálogo para el primer nivel del filtro.
     */
    public function getZonesProperty(): array
    {
        $zones = [];
        foreach ($this->routes as $route) {
            $zones[$route['zone_id']] = [
                'zone_id' => $route['zone_id'],
                'zone_name' => $route['zone_name'] ?? "Zona {$route['zone_id']}",
            ];
        }

        return array_values($zones);
    }

    /**
     * Rutas del catálogo filtradas por la zona seleccionada (cascada zona → ruta).
     * El stub no filtra server-side, así que el filtro vive aquí.
     */
    public function getFilteredRoutesProperty(): array
    {
        $routes = $this->routes;

        if ($this->zoneId !== null) {
            $routes = array_values(array_filter(
                $routes,
                fn (array $route): bool => (int) ($route['zone_id'] ?? 0) === $this->zoneId,
            ));
        }

        return $routes;
    }

    /**
     * Rutas monitoreadas, filtradas por la zona (vía el mapeo route → zone del
     * catálogo) y por la ruta seleccionada (si aplica). El stub no filtra
     * server-side, así que el filtro vive aquí.
     */
    public function getFilteredMonitoredProperty(): array
    {
        $monitored = $this->monitored;

        if ($this->zoneId !== null) {
            $zoneOfRoute = [];
            foreach ($this->routes as $route) {
                $zoneOfRoute[(int) ($route['route_id'] ?? 0)] = (int) ($route['zone_id'] ?? 0);
            }

            $monitored = array_values(array_filter(
                $monitored,
                fn (array $route): bool => ($zoneOfRoute[(int) ($route['route_id'] ?? 0)] ?? 0) === $this->zoneId,
            ));
        }

        if ($this->routeId !== null) {
            $monitored = array_values(array_filter(
                $monitored,
                fn (array $route): bool => (int) ($route['route_id'] ?? 0) === $this->routeId,
            ));
        }

        return $monitored;
    }

    /**
     * RouteMonitorListResponse: rutas con posición, última venta y estado.
     */
    public function getMonitoredProperty(): array
    {
        $result = app(RouteMonitorService::class)->monitor($this->filterPayload());

        return $result['success'] ? ($result['data'] ?? []) : [];
    }

    /**
     * Marcadores para <x-map-view>: lat/lon numéricos y estado del contrato.
     * La transformación vive aquí, nunca en la vista (regla de vista tonta).
     */
    public function getMarkersProperty(): array
    {
        return array_values(array_map(
            fn (array $route): array => [
                'lat' => (float) ($route['latitude'] ?? 0),
                'lon' => (float) ($route['longitude'] ?? 0),
                'status' => $route['status'] ?? 'unknown',
                'label' => ($route['route_name'] ?? 'Ruta')
                    .' · vendedor '.($route['seller'] ?? '—')
                    .' · última venta '.($route['last_sale']['at'] ?? 'sin ventas'),
            ],
            $this->filteredMonitored,
        ));
    }

    /**
     * KPIs del panel lateral: una tarjeta por ruta monitoreada.
     */
    public function getSideKpisProperty(): array
    {
        return array_values(array_map(
            fn (array $route): array => [
                'title' => $route['route_name'] ?? 'Ruta',
                'value' => $route['last_sale']['at'] ?? 'Sin ventas',
                'delta' => $route['seller'] ?? null,
                'status' => $route['status'] ?? 'unknown',
            ],
            $this->filteredMonitored,
        ));
    }

    /**
     * Filtra el catálogo de rutas por la zona seleccionada (cascada).
     */
    public function updatedZoneId(): void
    {
        // Al cambiar de zona se limpia la ruta, que puede no pertenecer a ella.
        $this->routeId = null;
    }

    /**
     * Dispara la consulta desde el formulario de filtros: valida el estado
     * actual con las reglas de ReportFilterRequest antes de refrescar.
     */
    public function consultar()
    {
        $validator = Validator::make($this->filterInput(), (new ReportFilterRequest)->rules());

        if ($validator->fails()) {
            $this->dispatch('rutx:feedback', Feedback::error('Revisa los filtros del mapa.'));
            $this->setErrorBag($validator->errors());

            return;
        }

        $this->resetErrorBag();
        $this->refreshMap();
    }

    /**
     * Despacha el evento JS para actualizar los marcadores del mapa (guidelines §4).
     */
    public function refreshMap(): void
    {
        $this->dispatch('rutx:refresh-maps');
    }

    /**
     * Restablece los filtros a su valor inicial (conserva el panel abierto).
     */
    public function limpiar()
    {
        $this->reset(['zoneId', 'routeId']);
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
            'zone_id' => $this->zoneId,
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
