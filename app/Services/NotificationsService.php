<?php

declare(strict_types=1);

namespace App\Services;

/**
 * NotificationsService — bandeja y emisión de avisos (contrato v2 §6.4).
 *
 * GET  /api/v2/web/notifications        → bandeja.
 * GET  /api/v2/web/notifications/count  → contador activo (campana del topbar).
 * POST /api/v2/web/notifications        → emisión; SIEMPRE con Idempotency-Key.
 *
 * La clave de idempotencia se conserva durante el intento de comando y solo se
 * invalida después de una respuesta exitosa (plan §5.3): un doble clic no
 * produce dos avisos; un 409 se muestra como conflicto funcional.
 */
class NotificationsService
{
    public function __construct(private readonly ApiClient $api) {}

    /**
     * GET /api/v2/web/notifications con filtros ya validados.
     *
     * @param  array<string, mixed>  $filters
     * @return array{success: bool, data?: array, meta?: array, code?: string, message?: string}
     */
    public function list(array $filters): array
    {
        return $this->api->get('/notifications', $filters);
    }

    /**
     * GET /api/v2/web/notifications/count.
     *
     * @return array{success: bool, data?: array, code?: string, message?: string}
     */
    public function countActive(): array
    {
        return $this->api->get('/notifications/count');
    }

    /**
     * POST /api/v2/web/notifications con Idempotency-Key obligatoria.
     *
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, data?: array, code?: string, message?: string}
     */
    public function send(array $payload, string $idempotencyKey): array
    {
        return $this->api->post('/notifications', $payload, [
            'Idempotency-Key' => $idempotencyKey,
        ]);
    }
}
