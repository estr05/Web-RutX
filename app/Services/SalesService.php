<?php

declare(strict_types=1);

namespace App\Services;

/**
 * SalesService — Consulta de transacciones (ventas, cobranza, devoluciones) (contrato v2).
 *
 * GET /api/v2/web/sales
 * GET /api/v2/web/sales/{id}
 */
final class SalesService
{
    public function __construct(private readonly ApiClient $api) {}

    public function list(array $filters = []): array
    {
        return $this->api->get('/sales', $filters);
    }

    public function show(int $saleId): array
    {
        return $this->api->get("/sales/{$saleId}");
    }
}
