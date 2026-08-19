<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\DashboardService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

/**
 * DashboardServiceTest
 *
 * Cubre ambos modos del servicio:
 * - @stub: con stubs_enabled=true devuelve la forma exacta de los DTO del
 *   contrato v2 sin tocar HTTP (Http::assertNothingSent).
 * - Real: con stubs_enabled=false consulta GET /api/v2/web/dashboard y
 *   /dashboard/sales-series vía ApiClient; errores 422/500 se traducen a
 *   success=false con mensaje funcional y trace_id logueado en api_errors.
 */
class DashboardServiceTest extends TestCase
{
    private const BASE_URL = 'https://sincronizador.example.test/api/v2/web';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.api_web', [
            'base_url' => self::BASE_URL,
            'timeout' => 10,
            'connect_timeout' => 5,
            'verify_tls' => true,
            'stubs_enabled' => false,
        ]);

        $this->session(['api_token' => 'web-token-test']);
    }

    // -------------------------------------------------------------------------
    // Modo @stub — forma exacta del contrato sin HTTP
    // -------------------------------------------------------------------------

    public function test_stub_summary_returns_contract_shape_without_http(): void
    {
        config()->set('services.api_web.stubs_enabled', true);

        $result = app(DashboardService::class)->summary(['range' => 'diario']);

        $this->assertTrue($result['success']);
        $this->assertCount(7, $result['data']['kpi']);
        $this->assertSame('MXN', $result['data']['meta']['currency']);
        $this->assertNull($result['data']['meta']['last_sync_at']);

        foreach ($result['data']['kpi'] as $kpi) {
            $this->assertSame('unknown', $kpi['status']);
            $this->assertArrayHasKey('delta', $kpi);
        }

        Http::assertNothingSent();
    }

    public function test_stub_sales_series_returns_contract_shape_without_http(): void
    {
        config()->set('services.api_web.stubs_enabled', true);

        $result = app(DashboardService::class)->salesSeries(['range' => 'semanal']);

        $this->assertTrue($result['success']);
        $this->assertSame('MXN', $result['data']['currency']);
        $this->assertSame('unknown', $result['data']['status']);
        $this->assertSame([], $result['data']['series']);

        Http::assertNothingSent();
    }

    // -------------------------------------------------------------------------
    // Endpoint real — data mapeado
    // -------------------------------------------------------------------------

    public function test_summary_hits_endpoint_and_returns_data(): void
    {
        Http::fake([
            '*/api/v2/web/dashboard*' => Http::response([
                'data' => [
                    'kpi' => [['label' => 'Venta total', 'value' => 15420.5, 'delta' => null, 'status' => 'ok']],
                    'meta' => ['last_sync_at' => '2026-08-14T10:00:00-06:00', 'currency' => 'MXN'],
                ],
                'trace_id' => '01J-dash-ok',
            ]),
        ]);

        $result = app(DashboardService::class)->summary(['range' => 'diario']);

        $this->assertTrue($result['success']);
        $this->assertSame('MXN', $result['data']['meta']['currency']);
        $this->assertSame(15420.5, $result['data']['kpi'][0]['value']);

        Http::assertSent(function (Request $request): bool {
            return str_starts_with($request->url(), self::BASE_URL.'/dashboard')
                && str_contains($request->url(), 'range=diario');
        });
    }

    public function test_sales_series_hits_endpoint_and_returns_data(): void
    {
        Http::fake([
            '*/api/v2/web/dashboard/sales-series*' => Http::response([
                'data' => [
                    'series' => [['period' => '2026-08-14', 'amount' => 1234.5]],
                    'currency' => 'MXN',
                    'status' => 'ok',
                ],
                'trace_id' => '01J-series-ok',
            ]),
        ]);

        $result = app(DashboardService::class)->salesSeries(['range' => 'mensual']);

        $this->assertTrue($result['success']);
        $this->assertSame(1234.5, $result['data']['series'][0]['amount']);

        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), self::BASE_URL.'/dashboard/sales-series'));
    }

    // -------------------------------------------------------------------------
    // Errores — mensaje funcional y trazabilidad
    // -------------------------------------------------------------------------

    public function test_422_returns_functional_error(): void
    {
        Http::fake([
            '*/api/v2/web/dashboard*' => Http::response([
                'code' => 'VALIDATION_ERROR',
                'message' => 'Filtro inválido.',
                'errors' => ['range' => ['El rango no es válido.']],
                'trace_id' => '01J-dash-422',
            ], 422),
        ]);

        $result = app(DashboardService::class)->summary(['range' => 'invalido']);

        $this->assertFalse($result['success']);
        $this->assertSame('VALIDATION_ERROR', $result['code']);
        $this->assertSame('Filtro inválido.', $result['message']);
        $this->assertSame(['range' => ['El rango no es válido.']], $result['errors']);
        $this->assertSame('01J-dash-422', $result['trace_id']);
    }

    public function test_500_logs_trace_id_in_api_errors_channel(): void
    {
        $logger = Mockery::mock();
        $logger->shouldReceive('error')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'ApiClient error'
                    && $context['code'] === 'API_UNAVAILABLE'
                    && $context['trace_id'] === '01J-dash-500';
            });

        Log::shouldReceive('channel')->with('api_errors')->once()->andReturn($logger);

        Http::fake([
            '*/api/v2/web/dashboard*' => Http::response([
                'code' => 'SERVER_ERROR',
                'message' => 'Error interno.',
                'trace_id' => '01J-dash-500',
            ], 500),
        ]);

        $result = app(DashboardService::class)->summary([]);

        $this->assertFalse($result['success']);
        $this->assertSame('01J-dash-500', $result['trace_id']);
    }
}
