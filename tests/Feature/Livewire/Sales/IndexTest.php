<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Sales;

use App\Livewire\Sales\Index;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class IndexTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.api_web.base_url', 'https://sincronizador.test/api/v2/web');
        $this->session(['api_token' => 'jwt-test']);
    }

    public function test_renders_and_loads_sales(): void
    {
        Http::fake([
            '*/api/v2/web/sales*' => Http::response([
                'success' => true,
                'data' => [
                    ['id' => 1, 'folio' => 'V-001', 'total_amount' => 500.0],
                ],
                'meta' => ['total' => 1, 'page' => 1, 'last_page' => 1],
            ], 200),
        ]);

        Livewire::test(Index::class)
            ->assertStatus(200)
            ->assertSet('sales.0.folio', 'V-001');
    }
}
