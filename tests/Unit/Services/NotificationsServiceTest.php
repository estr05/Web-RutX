<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\NotificationsService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * NotificationsServiceTest
 *
 * Valida bandeja, contador y emisión (contrato v2 §6.4):
 * - GET /notifications y /notifications/count.
 * - POST /notifications SIEMPRE con Idempotency-Key.
 * - Un 409 se conserva como conflicto funcional (no éxito).
 */
class NotificationsServiceTest extends TestCase
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

    public function test_list_keeps_envelope(): void
    {
        Http::fake([
            '*/api/v2/web/notifications*' => Http::response([
                'data' => [],
                'meta' => ['page' => 1, 'per_page' => 25, 'total' => 0, 'last_page' => 0],
                'filters' => ['page' => 1, 'per_page' => 25, 'status' => null, 'target_type' => null],
                'trace_id' => '01J-notif-list',
            ], 200),
        ]);

        $result = app(NotificationsService::class)->list(['per_page' => 25]);

        $this->assertTrue($result['success']);
        $this->assertSame([], $result['data']);
        $this->assertSame(0, $result['meta']['total']);
    }

    public function test_count_active_keeps_count(): void
    {
        Http::fake([
            '*/api/v2/web/notifications/count*' => Http::response([
                'data' => ['active_count' => 3],
                'trace_id' => '01J-notif-count',
            ], 200),
        ]);

        $result = app(NotificationsService::class)->countActive();

        $this->assertTrue($result['success']);
        $this->assertSame(3, $result['data']['active_count']);
    }

    public function test_send_always_sends_idempotency_key(): void
    {
        Http::fake([
            '*/api/v2/web/notifications*' => Http::response([
                'data' => ['created_count' => 1, 'notification_ids' => [7], 'target_type' => 'route'],
                'trace_id' => '01J-notif-created',
            ], 201),
        ]);

        $result = app(NotificationsService::class)->send([
            'target_type' => 'route',
            'target_ids' => [695],
            'title' => 'Aviso de prueba',
            'body' => 'Cuerpo de prueba',
            'priority' => 'normal',
        ], 'key-unica-001');

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['data']['created_count']);

        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/notifications')
            && $request->hasHeader('Idempotency-Key')
            && $request->header('Idempotency-Key')[0] === 'key-unica-001');
    }

    public function test_send_409_is_functional_conflict_not_success(): void
    {
        Http::fake([
            '*/api/v2/web/notifications*' => Http::response([
                'code' => 'IDEMPOTENCY_CONFLICT',
                'message' => 'Esta Idempotency-Key ya fue utilizada.',
                'trace_id' => '01J-notif-409',
            ], 409),
        ]);

        $result = app(NotificationsService::class)->send([
            'target_type' => 'route',
            'target_ids' => [695],
            'title' => 'Aviso',
            'body' => 'Cuerpo',
        ], 'key-repetida');

        $this->assertFalse($result['success']);
        $this->assertSame('IDEMPOTENCY_CONFLICT', $result['code']);
        $this->assertArrayNotHasKey('data', $result);
    }
}
