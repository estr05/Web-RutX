<?php

declare(strict_types=1);

namespace App\Services;

/**
 * ReportsService — reportes consolidados del módulo Venta.
 *
 * Endpoints del contrato v2:
 *   GET /api/v2/web/reports/sales               → SalesReportResponse (agregados por ruta)
 *   GET /api/v2/web/reports/sales-comparison    → ComparisonResponse (dos series)
 *   GET /api/v2/web/reports/route-profitability → RouteProfitabilityResponse
 *
 * TODO: routeProfitability es Prioridad Posterior en el contrato v2 (§6.3); el
 *       Sincronizador aún no publica el endpoint, por eso su modo real queda
 *       pendiente de verificación contra el DTO final.
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
     *
     * Forma de data.by_route (agregados por ruta):
     *   ['route_name' => string, 'pieces' => int, 'cash_amount' => float,
     *    'credit_amount' => float, 'total_amount' => float]
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
                    'sales_amount' => 1500.00,
                    'pieces' => 30,
                    'currency' => 'MXN',
                ],
                'by_route' => [
                    [
                        'route_name' => 'Ruta Stub Norte',
                        'pieces' => 15,
                        'cash_amount' => 500.00,
                        'credit_amount' => 200.00,
                        'total_amount' => 700.00,
                    ],
                    [
                        'route_name' => 'Ruta Stub Sur',
                        'pieces' => 15,
                        'cash_amount' => 400.00,
                        'credit_amount' => 400.00,
                        'total_amount' => 800.00,
                    ]
                ],
                'status' => 'unknown',
            ],
        ];
    }

    /**
     * GET /api/v2/web/reports/sales-comparison → ComparisonResponse (dos series).
     *
     * Forma de data (series comparables del periodo actual y del mismo periodo
     * del año anterior):
     *   ['current'  => [['period' => string, 'amount' => float], ...],
     *    'previous' => [['period' => string, 'amount' => float], ...],
     *    'currency' => 'MXN', 'status' => string]
     */
    public function salesComparison(array $filters): array
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
     * GET /api/v2/web/reports/route-profitability → rentabilidad por ruta.
     *
     * @stub — Endpoint Prioridad Posterior en el contrato v2 (§6.3); el
     *         Sincronizador no lo publica aún. Devuelve la estructura esperada
     *         (agregados de venta, gasto, entrega y costo disponible) con
     *         valores cero/unknown. TODO: verificar el DTO final cuando exista.
     */
    public function routeProfitability(array $filters): array
    {
        if (! config('services.api_web.stubs_enabled', false)) {
            return $this->resolve($this->api->get('/reports/route-profitability', $filters));
        }

        return [
            'success' => true,
            'data' => [
                'totals' => [
                    'sales_amount' => 0.00,
                    'expense_amount' => 0.00,
                    'delivery_amount' => 0.00,
                    'cost_amount' => 0.00,
                    'profit_amount' => 0.00,
                    'currency' => 'MXN',
                ],
                'by_route' => [],
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
