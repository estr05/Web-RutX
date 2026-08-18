<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\CustomersService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * CustomersServiceTest
 *
 * Valida el adaptador de clientes (contrato v2 §6.2):
 * - GET /customers con filtros snake_case ya validados.
 * - El envelope de éxito se conserva con data + meta.
 * - Los errores funcionales se propagan sin fuga de payload.
 */
class CustomersServiceTest extends TestCase
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
        ]);

        $this->session(['api_token' => 'jwt-web-scope']);
    }

    public function test_list_sends_validated_filters_and_keeps_meta(): void
    {
        Http::fake([
            '*/api/v2/web/customers*' => Http::response([
                'data' => [
                    ['customer_id' => 2576, 'code' => '2576', 'name' => 'CLIENTE A CONTADO 02',
                        'zone_id' => 3794, 'zone_name' => 'ORIENTE', 'route_id' => 695,
                        'route_name' => 'VENDEDOR', 'seller_name' => 'VENDEDOR', 'status' => 'A'],
                ],
                'meta' => ['page' => 1, 'per_page' => 25, 'total' => 1, 'last_page' => 1],
                'filters' => ['search' => null, 'zone_id' => null, 'route_id' => 695, 'status' => null],
                'trace_id' => '01J-customers-ok',
            ], 200),
        ]);

        $result = app(CustomersService::class)->list(['route_id' => 695, 'per_page' => 25]);

        $this->assertTrue($result['success']);
        $this->assertSame('2576', $result['data'][0]['code']);
        $this->assertSame(1, $result['meta']['total']);
        $this->assertSame('01J-customers-ok', $result['trace_id']);

        Http::assertSent(fn ($request): bool => $request->method() === 'GET'
            && str_contains($request->url(), '/api/v2/web/customers')
            && str_contains($request->url(), 'route_id=695')
            && str_contains($request->url(), 'per_page=25'));
    }

    public function test_list_403_propagates_functional_error_without_payload(): void
    {
        Http::fake([
            '*/api/v2/web/customers*' => Http::response([
                'code' => 'FORBIDDEN_ZONE',
                'message' => 'La zona solicitada no está dentro de las zonas autorizadas.',
                'trace_id' => '01J-customers-403',
            ], 403),
        ]);

        $result = app(CustomersService::class)->list(['zone_id' => 3795]);

        $this->assertFalse($result['success']);
        $this->assertSame('FORBIDDEN_ZONE', $result['code']);
        $this->assertSame('Ocurrió un error en el servicio.', $result['message']);
        $this->assertSame('01J-customers-403', $result['trace_id']);
        $this->assertArrayNotHasKey('data', $result);
    }
}
