<?php

declare(strict_types=1);

namespace App\Services;

/**
 * ReportsService — reportes consolidados del módulo Venta.
 *
 * Endpoints del contrato v2:
 *   GET /api/v2/web/reports/sales            → SalesReportResponse (agregados por ruta)
 *   GET /api/v2/web/reports/sales-comparison → ComparisonResponse (dos series)
 *
 * @stub — Mientras el Sincronizador no publique estos endpoints (activar con
 *         services.api_web.stubs_enabled = true), devuelve la estructura exacta
 *         de los DTO del contrato v2 con valores cero/unknown. La interfaz no
 *         cambia cuando se conecten los endpoints reales.
 */
class ReportsService
{
    public function __construct(private readonly ApiClient $api) {}

    /**
     * GET /api/v2/web/reports/sales → SalesReportResponse (ventas, piezas y montos).
     */
    public function report(array $filters): array
    {
        // Path relativo: ApiClient ya lleva la base con /api/v2/web.
        if (! config('services.api_web.stubs_enabled', false)) {
            return $this->resolve($this->api->get('/reports/sales', $filters));
        }

        return [
            'success' => true,
            'data' => [
                'totals' => [
                    'sales_amount' => 0.00,
                    'pieces' => 0,
                    'currency' => 'MXN',
                ],
                'by_route' => [],
                'status' => 'unknown',
            ],
        ];
    }

    /**
     * GET /api/v2/web/reports/sales-comparison → ComparisonResponse (dos series).
     */
    public function comparison(array $filters): array
    {
        // Path relativo: ApiClient ya lleva la base con /api/v2/web.
        if (! config('services.api_web.stubs_enabled', false)) {
            return $this->resolve($this->api->get('/reports/sales-comparison', $filters));
        }

        return [
            'success' => true,
            'data' => [
                'current' => [],
                'previous' => [],
                'currency' => 'MXN',
                'status' => 'unknown',
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
