<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\RouteMonitorService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

/**
 * RouteMonitorServiceTest
 *
 * Cubre ambos modos del servicio:
 * - @stub: stubs_enabled=true devuelve los DTO de RouteMonitor del contrato v2 sin HTTP.
 * - Real: consulta /api/v2/web/route-monitor, /route-monitor/{id} y /routes;
 *   errores 422/500 → success=false, mensaje funcional y trace_id en api_errors.
 */
class RouteMonitorServiceTest extends TestCase
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

    public function test_stub_monitor_returns_contract_shape_without_http(): void
    {
        config()->set('services.api_web.stubs_enabled', true);

        $result = app(RouteMonitorService::class)->monitor(['zone_id' => 1]);

        $this->assertTrue($result['success']);
        $this->assertCount(3, $result['data']);
        $this->assertIsFloat($result['data'][0]['latitude']);
        $this->assertIsFloat($result['data'][0]['longitude']);
        $this->assertArrayHasKey('route_id', $result['data'][0]);
        $this->assertArrayHasKey('status', $result['data'][0]);

        Http::assertNothingSent();
    }

    public function test_stub_route_detail_returns_contract_shape_without_http(): void
    {
        config()->set('services.api_web.stubs_enabled', true);

        $result = app(RouteMonitorService::class)->routeDetail(12);

        $this->assertTrue($result['success']);
        $this->assertSame(12, $result['data']['route_id']);
        $this->assertNull($result['data']['seller']);
        $this->assertNull($result['data']['last_sale']);
        $this->assertSame([], $result['data']['timeline']);
        $this->assertSame('unknown', $result['data']['status']);

        Http::assertNothingSent();
    }

    public function test_stub_routes_returns_contract_shape_without_http(): void
    {
        config()->set('services.api_web.stubs_enabled', true);

        $result = app(RouteMonitorService::class)->routes(['zone_id' => 1]);

        $this->assertTrue($result['success']);
        $this->assertCount(3, $result['data']);
        $this->assertArrayHasKey('route_id', $result['data'][0]);
        $this->assertArrayHasKey('route_name', $result['data'][0]);
        $this->assertArrayHasKey('zone_id', $result['data'][0]);

        Http::assertNothingSent();
    }

    // -------------------------------------------------------------------------
    // Endpoint real — data mapeado
    // -------------------------------------------------------------------------

    public function test_monitor_hits_endpoint_and_returns_data(): void
    {
        Http::fake([
            '*/api/v2/web/route-monitor*' => Http::response([
                'data' => [
                    ['route_id' => 1, 'seller' => 'María Hernández', 'last_sale' => null, 'status' => 'unknown'],
                ],
                'trace_id' => '01J-monitor-ok',
            ]),
        ]);

        $result = app(RouteMonitorService::class)->monitor(['zone_id' => 1]);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['data']);

        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), self::BASE_URL.'/route-monitor'));
    }

    public function test_route_detail_hits_endpoint_with_id(): void
    {
        Http::fake([
            '*/api/v2/web/route-monitor/12' => Http::response([
                'data' => [
                    'route_id' => 12,
                    'seller' => 'María Hernández',
                    'last_sale' => null,
                    'timeline' => [['type' => 'visit', 'at' => '2026-08-14T09:00:00-06:00']],
                    'status' => 'unknown',
                ],
                'trace_id' => '01J-detail-ok',
            ]),
        ]);

        $result = app(RouteMonitorService::class)->routeDetail(12);

        $this->assertTrue($result['success']);
        $this->assertSame(12, $result['data']['route_id']);
        $this->assertCount(1, $result['data']['timeline']);

        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), self::BASE_URL.'/route-monitor/12'));
    }

    public function test_routes_hits_endpoint_and_returns_catalog(): void
    {
        Http::fake([
            '*/api/v2/web/routes*' => Http::response([
                'data' => [['id' => 1, 'name' => 'Ruta Centro', 'status' => 'active']],
                'meta' => ['page' => 1, 'per_page' => 25, 'total' => 1, 'last_page' => 1],
                'trace_id' => '01J-routes-ok',
            ]),
        ]);

        $result = app(RouteMonitorService::class)->routes(['zone_id' => 1]);

        $this->assertTrue($result['success']);
        $this->assertSame('Ruta Centro', $result['data'][0]['name']);

        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), self::BASE_URL.'/routes'));
    }

    // -------------------------------------------------------------------------
    // Errores — mensaje funcional y trazabilidad
    // -------------------------------------------------------------------------

    public function test_422_returns_functional_error(): void
    {
        Http::fake([
            '*/api/v2/web/route-monitor*' => Http::response([
                'code' => 'VALIDATION_ERROR',
                'message' => 'Filtro inválido.',
                'errors' => ['zone_id' => ['Zona no autorizada.']],
                'trace_id' => '01J-monitor-422',
            ], 422),
        ]);

        $result = app(RouteMonitorService::class)->monitor(['zone_id' => 999]);

        $this->assertFalse($result['success']);
        $this->assertSame('No se pudo conectar con el servicio.', $result['message']);
        $this->assertSame('01J-monitor-422', $result['trace_id']);
    }

    public function test_500_logs_trace_id_in_api_errors_channel(): void
    {
        $logger = Mockery::mock();
        $logger->shouldReceive('error')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'ApiClient error'
                    && $context['code'] === 'API_UNAVAILABLE'
                    && $context['trace_id'] === '01J-monitor-500';
            });

        Log::shouldReceive('channel')->with('api_errors')->once()->andReturn($logger);

        Http::fake([
            '*/api/v2/web/routes*' => Http::response([
                'code' => 'SERVER_ERROR',
                'message' => 'Error interno.',
                'trace_id' => '01J-monitor-500',
            ], 500),
        ]);

        $result = app(RouteMonitorService::class)->routes([]);

        $this->assertFalse($result['success']);
        $this->assertSame('01J-monitor-500', $result['trace_id']);
    }
}
