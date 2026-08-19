<?php

declare(strict_types=1);

namespace App\Services;

/**
 * AgendaService — tablero semanal, acordeón y comando batch (contrato v2 §6.2).
 *
 * GET  /api/v2/web/agendas                              → tablero semanal.
 * GET  /api/v2/web/agendas/{date}/sellers/{id}/customers → clientes de un vendedor.
 * GET  /api/v2/web/agendas/unassigned-customers         → panel sin agenda.
 * PATCH /api/v2/web/agendas/assignments:batch           → batch con schedule_version.
 *
 * Reglas del contrato (Docs/Contrato y Nomenclatura §6.2 y §9.1):
 * - El batch SIEMPRE lleva Idempotency-Key y schedule_version.
 * - Un 409 SCHEDULE_VERSION_CONFLICT indica edición concurrente; la UI debe
 *   recargar el tablero conservando los cambios pendientes.
 * - Un 409 IDEMPOTENCY_CONFLICT indica operación ya procesada; no se genera
 *   una clave nueva automáticamente.
 * - La clave de idempotencia solo se invalida tras una respuesta exitosa.
 */
final class AgendaService
{
    public function __construct(private readonly ApiClient $api) {}

    /**
     * Tablero semanal con días, vendedores y contadores de clientes.
     * Puede incluir catálogo de zonas/rutas en data.options (maqueta §1.2).
     *
     * @param  array<string, mixed>  $filters  zone_id, route_id, date_from, date_to, page, per_page
     * @return array{success: bool, data?: array, meta?: array, filters?: array, code?: string, message?: string}
     */
    public function board(array $filters): array
    {
        return $this->api->get('/agendas', $filters);
    }

    /**
     * Clientes asignados a un vendedor en una fecha específica (acordeón).
     *
     * @param  array<string, mixed>  $filters  page, per_page
     * @return array{success: bool, data?: array, meta?: array, code?: string, message?: string}
     */
    public function sellerCustomers(string $agendaDate, int $sellerId, array $filters = []): array
    {
        return $this->api->get(
            "/agendas/{$agendaDate}/sellers/{$sellerId}/customers",
            $filters,
        );
    }

    /**
     * Panel lateral de clientes sin agenda asignada.
     *
     * @param  array<string, mixed>  $filters  search, zone_id, route_id, page, per_page
     * @return array{success: bool, data?: array, meta?: array, code?: string, message?: string}
     */
    public function unassignedCustomers(array $filters): array
    {
        return $this->api->get('/agendas/unassigned-customers', $filters);
    }

    /**
     * Guarda movimientos del tablero en un único comando atómico.
     *
     * @param  array<string, mixed>  $payload  schedule_version + assignments[]
     * @return array{success: bool, data?: array, code?: string, message?: string}
     */
    public function assignBatch(array $payload, string $idempotencyKey): array
    {
        return $this->api->patch(
            '/agendas/assignments:batch',
            $payload,
            ['Idempotency-Key' => $idempotencyKey],
        );
    }
}
