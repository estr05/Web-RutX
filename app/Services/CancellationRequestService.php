<?php

declare(strict_types=1);

namespace App\Services;

/**
 * CancellationRequestService — Solicitud de cancelación auditable (contrato v2).
 *
 * POST /api/v2/web/sales/{sale_id}/cancellation-requests
 */
final class CancellationRequestService
{
    public function __construct(private readonly ApiClient $api) {}

    public function create(int $saleId, array $payload, string $idempotencyKey): array
    {
        return $this->api->post(
            "/sales/{$saleId}/cancellation-requests",
            $payload,
            ['Idempotency-Key' => $idempotencyKey]
        );
    }
}
