<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\AgendaService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * AgendaServiceTest — valida tablero, acordeón, panel lateral y batch (contrato v2 §6.2).
 */
class AgendaServiceTest extends TestCase
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
        $this->session(['api_token' => 'jwt-web-agenda']);
    }

    public function test_board_sends_get_with_filters(): void
    {
        Http::fake([
            '*/api/v2/web/agendas*' => Http::response([
                'data' => ['schedule_version' => 42, 'days' => []],
                'meta' => ['page' => 1, 'per_page' => 7, 'total' => 7, 'last_page' => 1],
                'filters' => ['zone_id' => 1, 'route_id' => null, 'date_from' => '2026-08-17', 'date_to' => '2026-08-23'],
                'trace_id' => '01J-board-ok',
            ], 200),
        ]);

        $result = app(AgendaService::class)->board(['zone_id' => 1, 'date_from' => '2026-08-17', 'date_to' => '2026-08-23']);

        $this->assertTrue($result['success']);
        $this->assertSame(42, $result['data']['schedule_version']);
        $this->assertSame('01J-board-ok', $result['trace_id']);
        Http::assertSent(fn (Request $r): bool => $r->method() === 'GET'
            && str_contains($r->url(), '/agendas')
            && str_contains($r->url(), 'zone_id=1')
            && $r->hasHeader('Authorization', 'Bearer jwt-web-agenda'));
    }

    public function test_board_accepts_empty_filters(): void
    {
        Http::fake(['*/api/v2/web/agendas*' => Http::response(['data' => ['schedule_version' => 1, 'days' => []], 'trace_id' => '01J-board-empty'], 200)]);

        $result = app(AgendaService::class)->board([]);

        $this->assertTrue($result['success']);
        Http::assertSent(fn (Request $r): bool => $r->method() === 'GET' && str_ends_with(parse_url($r->url(), PHP_URL_PATH), '/agendas'));
    }

    public function test_board_propagates_error_envelope(): void
    {
        Http::fake(['*/api/v2/web/agendas*' => Http::response(['code' => 'NOT_IMPLEMENTED', 'message' => 'Funcionalidad no disponible.', 'trace_id' => '01J-board-501'], 501)]);

        $result = app(AgendaService::class)->board([]);

        $this->assertFalse($result['success']);
        $this->assertSame('NOT_IMPLEMENTED', $result['code']);
    }

    public function test_seller_customers_builds_correct_url(): void
    {
        Http::fake(['*/api/v2/web/agendas/2026-08-18/sellers/3572/customers*' => Http::response([
            'data' => ['agenda_date' => '2026-08-18', 'seller_id' => 3572, 'customers' => []],
            'meta' => ['page' => 1, 'per_page' => 25, 'total' => 0, 'last_page' => 0],
            'trace_id' => '01J-seller-ok',
        ], 200)]);

        $result = app(AgendaService::class)->sellerCustomers('2026-08-18', 3572);

        $this->assertTrue($result['success']);
        $this->assertSame('2026-08-18', $result['data']['agenda_date']);
        Http::assertSent(fn (Request $r): bool => $r->method() === 'GET' && str_contains($r->url(), '/agendas/2026-08-18/sellers/3572/customers'));
    }

    public function test_seller_customers_sends_pagination_filters(): void
    {
        Http::fake(['*/agendas/*/sellers/*/customers*' => Http::response(['data' => ['customers' => []], 'meta' => ['page' => 2, 'per_page' => 10, 'total' => 44, 'last_page' => 5], 'trace_id' => '01J-seller-page'], 200)]);

        app(AgendaService::class)->sellerCustomers('2026-08-18', 3572, ['page' => 2, 'per_page' => 10]);

        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'page=2') && str_contains($r->url(), 'per_page=10'));
    }

    public function test_unassigned_customers_sends_get_with_search(): void
    {
        Http::fake(['*/api/v2/web/agendas/unassigned-customers*' => Http::response(['data' => [], 'meta' => ['page' => 1, 'per_page' => 25, 'total' => 0, 'last_page' => 0], 'trace_id' => '01J-unassigned-ok'], 200)]);

        $result = app(AgendaService::class)->unassignedCustomers(['search' => 'diaz', 'route_id' => 3572]);

        $this->assertTrue($result['success']);
        Http::assertSent(fn (Request $r): bool => $r->method() === 'GET'
            && str_contains($r->url(), '/agendas/unassigned-customers')
            && str_contains($r->url(), 'search=diaz')
            && str_contains($r->url(), 'route_id=3572'));
    }

    public function test_assign_batch_sends_patch_with_idempotency_key(): void
    {
        Http::fake(['*/api/v2/web/agendas/assignments:batch' => Http::response(['data' => ['schedule_version' => 43, 'results' => []], 'trace_id' => '01J-batch-ok'], 200)]);

        $payload = ['schedule_version' => 42, 'assignments' => [['customer_id' => 123, 'action' => 'assign', 'seller_id' => 3572, 'agenda_date' => '2026-08-17']]];

        $result = app(AgendaService::class)->assignBatch($payload, 'idem-agenda-001');

        $this->assertTrue($result['success']);
        $this->assertSame(43, $result['data']['schedule_version']);
        Http::assertSent(function (Request $r): bool {
            return $r->method() === 'PATCH'
                && str_contains($r->url(), '/agendas/assignments:batch')
                && $r->hasHeader('Idempotency-Key', 'idem-agenda-001')
                && $r->data()['schedule_version'] === 42
                && count($r->data()['assignments']) === 1;
        });
    }

    public function test_assign_batch_maps_409_schedule_version_conflict(): void
    {
        Http::fake(['*/api/v2/web/agendas/assignments:batch' => Http::response(['code' => 'SCHEDULE_VERSION_CONFLICT', 'message' => 'La agenda fue modificada por otra sesion.', 'trace_id' => '01J-batch-409'], 409)]);

        $result = app(AgendaService::class)->assignBatch(['schedule_version' => 5, 'assignments' => []], 'idem-conflict');

        $this->assertFalse($result['success']);
        $this->assertSame('SCHEDULE_VERSION_CONFLICT', $result['code']);
    }

    public function test_assign_batch_maps_409_idempotency_conflict(): void
    {
        Http::fake(['*/api/v2/web/agendas/assignments:batch' => Http::response(['code' => 'IDEMPOTENCY_CONFLICT', 'message' => 'Esta operacion ya fue procesada.', 'trace_id' => '01J-batch-idem'], 409)]);

        $result = app(AgendaService::class)->assignBatch(['schedule_version' => 1, 'assignments' => []], 'idem-repeat');

        $this->assertFalse($result['success']);
        $this->assertSame('IDEMPOTENCY_CONFLICT', $result['code']);
    }

    public function test_assign_batch_maps_422_validation_error(): void
    {
        Http::fake(['*/api/v2/web/agendas/assignments:batch' => Http::response(['code' => 'VALIDATION_ERROR', 'message' => 'El campo assignments es obligatorio.', 'errors' => ['assignments' => ['El campo assignments es obligatorio.']], 'trace_id' => '01J-batch-422'], 422)]);

        $result = app(AgendaService::class)->assignBatch(['schedule_version' => 1, 'assignments' => []], 'idem-val');

        $this->assertFalse($result['success']);
        $this->assertSame('VALIDATION_ERROR', $result['code']);
        $this->assertNotNull($result['errors']);
    }
}
