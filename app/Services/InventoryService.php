<?php

declare(strict_types=1);

namespace App\Services;

/**
 * InventoryService — piloto de inventario por ruta (contrato v2 §6.4).
 *
 * GET /api/v2/web/inventory/by-route → InventoryRouteListResponse.
 *
 * Lectura estricta: el piloto no edita existencias, cierra rutas ni reconcilia
 * movimientos (plan §5.2). Si el endpoint no está habilitado, la vista debe
 * indicar "Integración de inventario pendiente de contrato v2" — el servicio
 * jamás inventa datos.
 */
class InventoryService
{
    public function __construct(private readonly ApiClient $api) {}

    /**
     * GET /api/v2/web/inventory/by-route con filtros ya validados (snake_case).
     *
     * @param  array<string, mixed>  $filters
     * @return array{success: bool, data?: array, meta?: array, code?: string, message?: string}
     */
    public function byRoute(array $filters): array
    {
        return $this->api->get('/inventory/by-route', $filters);
    }
}
