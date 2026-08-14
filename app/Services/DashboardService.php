<?php

declare(strict_types=1);

namespace App\Services;

/**
 * DashboardService — consultas del tablero Venta · Reportes y Gráficas.
 *
 * Endpoints del contrato v2:
 *   GET /api/v2/web/dashboard            → DashboardSummaryResponse (7 KPIs + meta)
 *   GET /api/v2/web/dashboard/sales-series → SalesSeriesResponse (serie para <x-chart>)
 *
 * @stub — Mientras el Sincronizador no publique estos endpoints (activar con
 *         services.api_web.stubs_enabled = true), devuelve la estructura exacta
 *         de los DTO del contrato v2 con valores cero/unknown. La interfaz no
 *         cambia cuando se conecten los endpoints reales.
 */
class DashboardService
{
    public function __construct(private readonly ApiClient $api) {}

    /**
     * GET /api/v2/web/dashboard → DashboardSummaryResponse (7 KPIs + meta).
     */
    public function summary(array $filters): array
    {
        // Path relativo: ApiClient ya lleva la base con /api/v2/web.
        if (! config('services.api_web.stubs_enabled', false)) {
            return $this->resolve($this->api->get('/dashboard', $filters));
        }

        return [
            'success' => true,
            'data' => [
                'kpi' => [
                    ['label' => 'Venta total', 'value' => 0.00, 'delta' => null, 'status' => 'unknown'],
                    ['label' => 'Contado', 'value' => 0.00, 'delta' => null, 'status' => 'unknown'],
                    ['label' => 'Crédito', 'value' => 0.00, 'delta' => null, 'status' => 'unknown'],
                    ['label' => 'Cobranza', 'value' => 0.00, 'delta' => null, 'status' => 'unknown'],
                    ['label' => 'No ventas', 'value' => 0, 'delta' => null, 'status' => 'unknown'],
                    ['label' => 'Entrega', 'value' => 0.00, 'delta' => null, 'status' => 'unknown'],
                    ['label' => 'Gastos', 'value' => 0.00, 'delta' => null, 'status' => 'unknown'],
                ],
                'meta' => ['last_sync_at' => null, 'currency' => 'MXN'],
            ],
        ];
    }

    /**
     * GET /api/v2/web/dashboard/sales-series → SalesSeriesResponse.
     */
    public function salesSeries(array $filters): array
    {
        // Path relativo: ApiClient ya lleva la base con /api/v2/web.
        if (! config('services.api_web.stubs_enabled', false)) {
            return $this->resolve($this->api->get('/dashboard/sales-series', $filters));
        }

        return [
            'success' => true,
            'data' => [
                'series' => [],
                'currency' => 'MXN',
                'status' => 'unknown',
            ],
        ];
    }

    /**
     * Normaliza la respuesta del ApiClient al contrato de pantalla:
     * success + data en éxito; success=false con código/mensaje/trace_id en error.
     */
    private function resolve(array $response): array
    {
        if (! $response['success']) {
            return ['success' => false] + $response;
        }

        return ['success' => true, 'data' => $response['data']];
    }
}
