<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Sales;

use App\Livewire\Sales\Show;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ShowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.api_web.base_url', 'https://sincronizador.test/api/v2/web');
        $this->session(['api_token' => 'jwt-test']);
    }

    public function test_renders_and_loads_sale_detail(): void
    {
        Http::fake([
            '*/api/v2/web/sales/123' => Http::response([
                'success' => true,
                'data' => ['id' => 123, 'folio' => 'V-123', 'status' => 'completed'],
            ], 200),
        ]);

        $this->session(['permissions' => ['sales.cancel']]);
        Livewire::test(Show::class, ['saleId' => 123])
            ->assertStatus(200)
            ->assertSet('sale.folio', 'V-123');
    }

    public function test_cancels_sale_successfully(): void
    {
        Http::fake([
            '*/api/v2/web/sales/123' => Http::response(['success' => true, 'data' => ['id' => 123, 'status' => 'completed']], 200),
            '*/api/v2/web/sales/123/cancellation-requests' => Http::response(['success' => true, 'data' => []], 200),
        ]);

        $this->session(['permissions' => ['sales.cancel']]);
        Livewire::test(Show::class, ['saleId' => 123])
            ->call('confirmCancellation')
            ->set('cancellationReason', 'Cliente se arrepintio de la compra, no hay fondos.')
            ->call('cancelSale')
            ->assertHasNoErrors()
            ->assertDispatched('rutx:feedback', type: 'success')
            ->assertSet('confirmingCancellation', false);
    }
}
