<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ReportsService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

/**
 * ReportsServiceTest
 *
 * Cubre ambos modos del servicio:
 * - @stub: stubs_enabled=true devuelve los DTO de Reports del contrato v2 sin HTTP.
 * - Real: consulta /api/v2/web/reports/sales y /reports/sales-comparison;
 *   errores 422/500 → success=false, mensaje funcional y trace_id en api_errors.
 */
class ReportsServiceTest extends TestCase
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

    public function test_stub_report_returns_contract_shape_without_http(): void
    {
        config()->set('services.api_web.stubs_enabled', true);

        $result = app(ReportsService::class)->report(['range' => 'diario']);

        $this->assertTrue($result['success']);
        $this->assertSame('MXN', $result['data']['totals']['currency']);
        $this->assertSame(0.00, $result['data']['totals']['sales_amount']);
        $this->assertSame(0, $result['data']['totals']['pieces']);
        $this->assertSame([], $result['data']['by_route']);
        $this->assertSame('unknown', $result['data']['status']);

        Http::assertNothingSent();
    }

    public function test_stub_comparison_returns_contract_shape_without_http(): void
    {
        config()->set('services.api_web.stubs_enabled', true);

        $result = app(ReportsService::class)->salesComparison(['range' => 'mensual']);

        $this->assertTrue($result['success']);
        $this->assertSame('MXN', $result['data']['currency']);
        $this->assertSame('unknown', $result['data']['status']);
        $this->assertSame([], $result['data']['current']);
        $this->assertSame([], $result['data']['previous']);

        Http::assertNothingSent();
    }

    public function test_stub_route_profitability_returns_contract_shape_without_http(): void
    {
        config()->set('services.api_web.stubs_enabled', true);

        $result = app(ReportsService::class)->routeProfitability(['range' => 'mensual']);

        $this->assertTrue($result['success']);
        $this->assertSame('MXN', $result['data']['totals']['currency']);
        $this->assertSame(0.00, $result['data']['totals']['sales_amount']);
        $this->assertSame(0.00, $result['data']['totals']['expense_amount']);
        $this->assertSame(0.00, $result['data']['totals']['profit_amount']);
        $this->assertSame([], $result['data']['by_route']);
        $this->assertSame('unknown', $result['data']['status']);

        Http::assertNothingSent();
    }

    // -------------------------------------------------------------------------
    // Endpoint real — data mapeado
    // -------------------------------------------------------------------------

    public function test_report_hits_endpoint_and_returns_data(): void
    {
        Http::fake([
            '*/api/v2/web/reports/sales*' => Http::response([
                'data' => [
                    'totals' => ['sales_amount' => 5000.0, 'pieces' => 120, 'currency' => 'MXN'],
                    'by_route' => [[
                        'route_name' => 'Ruta Centro',
                        'pieces' => 120,
                        'cash_amount' => 3200.0,
                        'credit_amount' => 1800.0,
                        'total_amount' => 5000.0,
                    ]],
                    'status' => 'ok',
                ],
                'trace_id' => '01J-report-ok',
            ]),
        ]);

        $result = app(ReportsService::class)->report(['route_id' => 1]);

        $this->assertTrue($result['success']);
        $this->assertSame(120, $result['data']['totals']['pieces']);
        $this->assertSame('Ruta Centro', $result['data']['by_route'][0]['route_name']);

        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), self::BASE_URL.'/reports/sales'));
    }

    public function test_report_with_empty_by_route_is_success_not_error(): void
    {
        Http::fake([
            '*/api/v2/web/reports/sales*' => Http::response([
                'data' => [
                    'totals' => ['sales_amount' => 0.0, 'pieces' => 0, 'currency' => 'MXN'],
                    'by_route' => [],
                    'status' => 'ok',
                ],
                'trace_id' => '01J-report-empty',
            ]),
        ]);

        $result = app(ReportsService::class)->report([]);

        $this->assertTrue($result['success']);
        $this->assertSame([], $result['data']['by_route']);
    }

    // -------------------------------------------------------------------------
    // Envelope malformado — INVALID_ENVELOPE sin exponer el body al usuario
    // -------------------------------------------------------------------------

    public function test_report_missing_by_route_returns_invalid_envelope(): void
    {
        $logger = Mockery::mock();
        $logger->shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return str_contains($message, '/reports/sales')
                    && $context['code'] === 'INVALID_ENVELOPE'
                    && $context['trace_id'] === '01J-report-bad';
            });

        Log::shouldReceive('channel')->with('api_errors')->once()->andReturn($logger);

        Http::fake([
            '*/api/v2/web/reports/sales*' => Http::response([
                'data' => ['status' => 'ok'], // falta by_route
                'trace_id' => '01J-report-bad',
            ]),
        ]);

        $result = app(ReportsService::class)->report([]);

        $this->assertFalse($result['success']);
        $this->assertSame('INVALID_ENVELOPE', $result['code']);
        $this->assertSame('El servicio respondió con una estructura inesperada.', $result['message']);
        $this->assertSame('01J-report-bad', $result['trace_id']);
    }

    public function test_report_row_missing_required_field_returns_invalid_envelope(): void
    {
        Log::shouldReceive('channel')->with('api_errors')->once()->andReturnUsing(function () {
            $logger = Mockery::mock();
            $logger->shouldReceive('warning')->once();

            return $logger;
        });

        Http::fake([
            '*/api/v2/web/reports/sales*' => Http::response([
                'data' => [
                    'by_route' => [[
                        'route_name' => 'Ruta Norte',
                        'pieces' => 10,
                        // falta cash_amount / credit_amount / total_amount
                        'total_amount' => 100.0,
                    ]],
                ],
                'trace_id' => '01J-report-row-bad',
            ]),
        ]);

        $result = app(ReportsService::class)->report([]);

        $this->assertFalse($result['success']);
        $this->assertSame('INVALID_ENVELOPE', $result['code']);
        // El mensaje funcional no filtra el payload crudo.
        $this->assertStringNotContainsString('Ruta Norte', (string) $result['message']);
    }

    public function test_report_non_array_by_route_returns_invalid_envelope(): void
    {
        Log::shouldReceive('channel')->with('api_errors')->once()->andReturnUsing(function () {
            $logger = Mockery::mock();
            $logger->shouldReceive('warning')->once();

            return $logger;
        });

        Http::fake([
            '*/api/v2/web/reports/sales*' => Http::response([
                'data' => ['by_route' => 'no-soy-arreglo'],
                'trace_id' => '01J-report-type-bad',
            ]),
        ]);

        $result = app(ReportsService::class)->report([]);

        $this->assertFalse($result['success']);
        $this->assertSame('INVALID_ENVELOPE', $result['code']);
        $this->assertSame('01J-report-type-bad', $result['trace_id']);
    }

    public function test_comparison_hits_endpoint_and_returns_data(): void
    {
        Http::fake([
            '*/api/v2/web/reports/sales-comparison*' => Http::response([
                'data' => [
                    'current' => [['period' => '2026-08-14', 'amount' => 100.0]],
                    'previous' => [['period' => '2026-08-07', 'amount' => 80.0]],
                    'currency' => 'MXN',
                    'status' => 'ok',
                ],
                'trace_id' => '01J-comparison-ok',
            ]),
        ]);

        $result = app(ReportsService::class)->salesComparison(['range' => 'semanal']);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['data']['previous']);

        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), self::BASE_URL.'/reports/sales-comparison'));
    }

    public function test_route_profitability_hits_endpoint_and_returns_data(): void
    {
        Http::fake([
            '*/api/v2/web/reports/route-profitability*' => Http::response([
                'data' => [
                    'totals' => ['sales_amount' => 10000.0, 'expense_amount' => 2000.0, 'delivery_amount' => 500.0, 'cost_amount' => 6000.0, 'profit_amount' => 1500.0, 'currency' => 'MXN'],
                    'by_route' => [['route_name' => 'Ruta Centro', 'sales_amount' => 10000.0, 'expense_amount' => 2000.0, 'delivery_amount' => 500.0, 'cost_amount' => 6000.0, 'profit_amount' => 1500.0]],
                    'status' => 'ok',
                ],
                'trace_id' => '01J-profit-ok',
            ]),
        ]);

        $result = app(ReportsService::class)->routeProfitability(['route_id' => 1]);

        $this->assertTrue($result['success']);
        $this->assertSame(1500, $result['data']['totals']['profit_amount']);

        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), self::BASE_URL.'/reports/route-profitability'));
    }

    // -------------------------------------------------------------------------
    // Errores — mensaje funcional y trazabilidad
    // -------------------------------------------------------------------------

    public function test_422_returns_functional_error(): void
    {
        Http::fake([
            '*/api/v2/web/reports/sales*' => Http::response([
                'code' => 'VALIDATION_ERROR',
                'message' => 'Filtro inválido.',
                'errors' => ['date_from' => ['Formato inválido.']],
                'trace_id' => '01J-report-422',
            ], 422),
        ]);

        $result = app(ReportsService::class)->report(['date_from' => '14/08/2026']);

        $this->assertFalse($result['success']);
        $this->assertSame('VALIDATION_ERROR', $result['code']);
        $this->assertSame('Filtro inválido.', $result['message']);
        $this->assertSame(['date_from' => ['Formato inválido.']], $result['errors']);
        $this->assertSame('01J-report-422', $result['trace_id']);
    }

    public function test_500_logs_trace_id_in_api_errors_channel(): void
    {
        $logger = Mockery::mock();
        $logger->shouldReceive('error')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'ApiClient error'
                    && $context['code'] === 'API_UNAVAILABLE'
                    && $context['trace_id'] === '01J-report-500';
            });

        Log::shouldReceive('channel')->with('api_errors')->once()->andReturn($logger);

        Http::fake([
            '*/api/v2/web/reports/sales*' => Http::response([
                'code' => 'SERVER_ERROR',
                'message' => 'Error interno.',
                'trace_id' => '01J-report-500',
            ], 500),
        ]);

        $result = app(ReportsService::class)->salesComparison([]);

        $this->assertFalse($result['success']);
        $this->assertSame('01J-report-500', $result['trace_id']);
    }

    public function test_route_profitability_500_logs_trace_id_in_api_errors_channel(): void
    {
        $logger = Mockery::mock();
        $logger->shouldReceive('error')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'ApiClient error'
                    && $context['code'] === 'API_UNAVAILABLE'
                    && $context['trace_id'] === '01J-profit-500';
            });

        Log::shouldReceive('channel')->with('api_errors')->once()->andReturn($logger);

        Http::fake([
            '*/api/v2/web/reports/route-profitability*' => Http::response([
                'code' => 'SERVER_ERROR',
                'message' => 'Error interno.',
                'trace_id' => '01J-profit-500',
            ], 500),
        ]);

        $result = app(ReportsService::class)->routeProfitability([]);

        $this->assertFalse($result['success']);
        $this->assertSame('01J-profit-500', $result['trace_id']);
    }
}
