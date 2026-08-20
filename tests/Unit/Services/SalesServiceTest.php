<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\SalesService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SalesServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.api_web.base_url', 'https://sincronizador.test/api/v2/web');
        $this->session(['api_token' => 'jwt-test']);
    }

    public function test_list_sends_get_request(): void
    {
        Http::fake(['*/api/v2/web/sales*' => Http::response(['success' => true, 'data' => []], 200)]);

        $result = app(SalesService::class)->list(['status' => 'completed']);

        $this->assertTrue($result['success']);
        Http::assertSent(fn (Request $r) => $r->method() === 'GET' && str_contains($r->url(), 'status=completed'));
    }

    public function test_show_sends_get_request(): void
    {
        Http::fake(['*/api/v2/web/sales/123' => Http::response(['success' => true, 'data' => ['id' => 123]], 200)]);

        $result = app(SalesService::class)->show(123);

        $this->assertTrue($result['success']);
        $this->assertSame(123, $result['data']['id']);
        Http::assertSent(fn (Request $r) => $r->method() === 'GET' && str_ends_with(parse_url($r->url(), PHP_URL_PATH), '/sales/123'));
    }
}
