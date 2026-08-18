<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\InventoryService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * InventoryServiceTest
 *
 * Valida el adaptador del piloto de inventario por ruta (contrato v2 §6.4):
 * - GET /inventory/by-route con route_id obligatorio.
 * - Envelope de éxito con filas de disponible/vendido/diferencia.
 * - Errores (NOT_FOUND de ruta, 503 de API) sin fuga de payload.
 */
class InventoryServiceTest extends TestCase
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

    public function test_by_route_sends_route_id_and_keeps_rows(): void
    {
        Http::fake([
            '*/api/v2/web/inventory/by-route*' => Http::response([
                'data' => [
                    ['product_id' => 2907, 'product_code' => '2907', 'product_name' => 'CHAMOY',
                        'unit' => 'Pieza', 'available' => 16, 'sold' => 4, 'difference' => 12],
                ],
                'meta' => ['page' => 1, 'per_page' => 5, 'total' => 1, 'last_page' => 1],
                'filters' => ['page' => 1, 'per_page' => 5, 'route_id' => 695, 'as_of' => '2026-08'],
                'trace_id' => '01J-inv-ok',
            ], 200),
        ]);

        $result = app(InventoryService::class)->byRoute([
            'route_id' => 695, 'as_of' => '2026-08', 'per_page' => 5,
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('CHAMOY', $result['data'][0]['product_name']);
        $this->assertSame(16, $result['data'][0]['available']);
        $this->assertSame(1, $result['meta']['total']);

        Http::assertSent(fn ($request): bool => $request->method() === 'GET'
            && str_contains($request->url(), '/api/v2/web/inventory/by-route')
            && str_contains($request->url(), 'route_id=695')
            && str_contains($request->url(), 'as_of=2026-08'));
    }

    public function test_by_route_not_found_propagates_code_without_payload(): void
    {
        Http::fake([
            '*/api/v2/web/inventory/by-route*' => Http::response([
                'code' => 'NOT_FOUND',
                'message' => 'La ruta no tiene un almacén de operación reciente.',
                'trace_id' => '01J-inv-404',
            ], 404),
        ]);

        $result = app(InventoryService::class)->byRoute(['route_id' => 99999]);

        $this->assertFalse($result['success']);
        $this->assertSame('NOT_FOUND', $result['code']);
        $this->assertArrayNotHasKey('data', $result);
    }
}
