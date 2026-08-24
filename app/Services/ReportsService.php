<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;

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
            return $this->resolveSalesReport($this->api->get('/reports/sales', $filters));
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

    /**
     * Normaliza la respuesta de /reports/sales validando la forma contractual
     * de data.by_route antes de exponerla a la interfaz.
     *
     * Tres casos distinguibles:
     *   1. success=false (API caída, 4xx/5xx) → se propaga code/message/trace_id.
     *   2. Envelope malformado → INVALID_ENVELOPE con mensaje funcional;
     *      el detalle queda solo en el canal api_errors (nunca en pantalla).
     *   3. Éxito con by_route vacío ([] ) → éxito legítimo, tabla vacía.
     */
    private function resolveSalesReport(array $response): array
    {
        if (! ($response['success'] ?? false)) {
            return ['success' => false] + $response;
        }

        $data = $response['data'] ?? null;

        if (! $this->salesByRouteIsValid($data)) {
            Log::channel('api_errors')->warning('Envelope de /reports/sales inválido.', [
                'code' => 'INVALID_ENVELOPE',
                'trace_id' => $response['trace_id'] ?? null,
            ]);

            return [
                'success' => false,
                'code' => 'INVALID_ENVELOPE',
                'message' => __('El servicio respondió con una estructura inesperada.'),
                'errors' => null,
                'trace_id' => $response['trace_id'] ?? null,
            ];
        }

        return [
            'success' => true,
            'data' => [
                'totals' => is_array($data['totals'] ?? null) ? $data['totals'] : [],
                'by_route' => $data['by_route'],
                'status' => is_string($data['status'] ?? null) ? $data['status'] : 'ok',
            ],
        ];
    }

    /**
     * Valida data.by_route contra el contrato v2: cada fila requiere
     * route_name, pieces, cash_amount, credit_amount y total_amount.
     * Un by_route vacío es válido (período sin ventas).
     *
     * @param  mixed  $data  Contenido de `data` del envelope de éxito.
     */
    private function salesByRouteIsValid(mixed $data): bool
    {
        if (! is_array($data) || ! isset($data['by_route']) || ! is_array($data['by_route'])) {
            return false;
        }

        foreach ($data['by_route'] as $row) {
            if (! is_array($row)
                || ! array_key_exists('route_name', $row)
                || ! isset($row['pieces'], $row['cash_amount'], $row['credit_amount'], $row['total_amount'])
            ) {
                return false;
            }
        }

        return true;
    }
}
