<?php

declare(strict_types=1);

namespace App\Services;

/**
 * RouteMonitorService — supervisión de ruta en vivo (módulo Ruta).
 *
 * Endpoints del contrato v2:
 *   GET /api/v2/web/route-monitor        → RouteMonitorListResponse (mapa en vivo)
 *   GET /api/v2/web/route-monitor/{id}   → RouteTimelineResponse (detalle de ruta)
 *   GET /api/v2/web/routes               → catálogo paginado para el filtro cascada zona → ruta
 *
 * Forma prevista de cada ítem de RouteMonitorListResponse:
 *   ['route_id' => int, 'seller' => string|null, 'last_sale' => array|null,
 *    'workday_started_at' => string|null, 'latitude' => float|null,
 *    'longitude' => float|null, 'status' => 'active'|'unknown'|...]
 *
 * @stub — Mientras el Sincronizador no publique estos endpoints (activar con
 *         services.api_web.stubs_enabled = true), devuelve la estructura exacta
 *         de los DTO del contrato v2 con valores cero/unknown. La interfaz no
 *         cambia cuando se conecten los endpoints reales.
 */
class RouteMonitorService
{
    public function __construct(private readonly ApiClient $api) {}

    /**
     * GET /api/v2/web/route-monitor → RouteMonitorListResponse (estado por ruta).
     */
    public function monitor(array $filters): array
    {
        // Path relativo: ApiClient ya lleva la base con /api/v2/web.
        if (! config('services.api_web.stubs_enabled', false)) {
            return $this->resolve($this->api->get('/route-monitor', $filters));
        }

        // @stub: tres rutas de fixture con posición numérica (zona de
        // Guadalajara, centro del mapa) para que el mapa demuestre marcadores
        // sin inventar ubicaciones en la vista.
        return [
            'success' => true,
            'data' => [
                [
                    'route_id' => 1,
                    'route_name' => 'Ruta Norte',
                    'seller' => 'María Hernández',
                    'last_sale' => ['at' => '2026-08-14T10:30:00-06:00'],
                    'workday_started_at' => '2026-08-14T08:00:00-06:00',
                    'latitude' => 20.663,
                    'longitude' => -103.352,
                    'status' => 'active',
                ],
                [
                    'route_id' => 2,
                    'route_name' => 'Ruta Sur',
                    'seller' => 'Pedro López',
                    'last_sale' => null,
                    'workday_started_at' => '2026-08-14T08:15:00-06:00',
                    'latitude' => 20.671,
                    'longitude' => -103.391,
                    'status' => 'delayed',
                ],
                [
                    'route_id' => 3,
                    'route_name' => 'Ruta Centro',
                    'seller' => 'Ana Torres',
                    'last_sale' => ['at' => '2026-08-14T11:05:00-06:00'],
                    'workday_started_at' => '2026-08-14T08:00:00-06:00',
                    'latitude' => 20.637,
                    'longitude' => -103.425,
                    'status' => 'stopped',
                ],
            ],
        ];
    }

    /**
     * GET /api/v2/web/route-monitor/{id} → RouteTimelineResponse.
     */
    public function routeDetail(int $routeId): array
    {
        // Path relativo: ApiClient ya lleva la base con /api/v2/web.
        if (! config('services.api_web.stubs_enabled', false)) {
            return $this->resolve($this->api->get("/route-monitor/{$routeId}"));
        }

        return [
            'success' => true,
            'data' => [
                'route_id' => $routeId,
                'seller' => null,
                'last_sale' => null,
                'timeline' => [],
                'status' => 'unknown',
            ],
        ];
    }

    /**
     * GET /api/v2/web/routes → catálogo paginado de rutas para el filtro cascada.
     */
    public function routes(array $filters): array
    {
        // Path relativo: ApiClient ya lleva la base con /api/v2/web.
        if (! config('services.api_web.stubs_enabled', false)) {
            return $this->resolve($this->api->get('/routes', $filters));
        }

        // @stub: catálogo de las tres rutas del monitor, con su zona, para
        // alimentar el filtro cascada zona → ruta.
        return [
            'success' => true,
            'data' => [
                ['route_id' => 1, 'route_name' => 'Ruta Norte', 'zone_id' => 1, 'zone_name' => 'Zona Norte', 'status' => 'active'],
                ['route_id' => 2, 'route_name' => 'Ruta Sur', 'zone_id' => 2, 'zone_name' => 'Zona Sur', 'status' => 'active'],
                ['route_id' => 3, 'route_name' => 'Ruta Centro', 'zone_id' => 1, 'zone_name' => 'Zona Norte', 'status' => 'active'],
            ],
        ];
    }

    /**
     * Normaliza la respuesta del ApiClient al contrato de pantalla.
     */
    private function resolve(array $response): array
    {
        if (! $response['success']) {
            return ['success' => false] + $response;
        }

        return ['success' => true, 'data' => $response['data']];
    }
}
