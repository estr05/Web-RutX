<?php

declare(strict_types=1);

namespace App\Services;

/**
 * CustomersService — catálogo de clientes del portal (contrato v2 §6.2).
 *
 * GET /api/v2/web/customers → CustomerListResponse (data + meta + filters).
 *
 * El servicio solo conserva la respuesta, la meta de paginación y un estado
 * funcional para la vista (plan §5.1). La intersección de zonas la aplica el
 * Sincronizador contra el JWT; Laravel no convierte permisos en filtros.
 */
class CustomersService
{
    public function __construct(private readonly ApiClient $api) {}

    /**
     * GET /api/v2/web/customers con filtros ya validados (snake_case).
     *
     * @param  array<string, mixed>  $filters
     * @return array{success: bool, data?: array, meta?: array, code?: string, message?: string}
     */
    public function list(array $filters): array
    {
        return $this->api->get('/customers', $filters);
    }
}
