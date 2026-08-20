<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\CancellationRequestService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CancellationRequestServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.api_web.base_url', 'https://sincronizador.test/api/v2/web');
        $this->session(['api_token' => 'jwt-test']);
    }

    public function test_create_sends_post_with_idempotency_key(): void
    {
        Http::fake(['*/api/v2/web/sales/456/cancellation-requests' => Http::response(['success' => true, 'data' => []], 200)]);

        $result = app(CancellationRequestService::class)->create(456, ['reason' => 'Cliente canceló el pedido'], 'idem-cancel-01');

        $this->assertTrue($result['success']);
        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && str_ends_with(parse_url($r->url(), PHP_URL_PATH), '/sales/456/cancellation-requests')
            && $r->hasHeader('Idempotency-Key', 'idem-cancel-01')
            && $r->data()['reason'] === 'Cliente canceló el pedido'
        );
    }
}
