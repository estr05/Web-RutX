<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Sales;

use App\Livewire\Sales\Show;
use Illuminate\Auth\GenericUser;
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
            '*/api/v2/web/sales/123/cancellation-requests' => Http::response(['success' => true, 'data' => []], 200),
            '*/api/v2/web/sales/123' => Http::response(['success' => true, 'data' => ['id' => 123, 'folio' => 'V-123', 'status' => 'completed']], 200),
        ]);

        $this->actingAs(new GenericUser(['id' => 1]));
        $this->session(['permissions' => ['sales.cancel']]);

        Livewire::test(Show::class, ['saleId' => 123])
            ->call('confirmCancellation')
            ->assertSet('confirmingCancellation', true)
            ->set('cancellationReason', 'Cliente se arrepintio de la compra, no hay fondos.')
            ->call('cancelSale')
            ->assertHasNoErrors()
            ->assertDispatched('rutx:feedback', type: 'success')
            ->assertSet('confirmingCancellation', false);
    }

    public function test_confirm_opens_cancellation_modal(): void
    {
        Http::fake([
            '*/api/v2/web/sales/456' => Http::response([
                'success' => true,
                'data' => ['id' => 456, 'folio' => 'V-456', 'status' => 'completed'],
            ], 200),
        ]);

        $this->actingAs(new GenericUser(['id' => 1]));
        $this->session(['permissions' => ['sales.cancel']]);

        Livewire::test(Show::class, ['saleId' => 456])
            ->assertSet('confirmingCancellation', false)
            ->call('confirmCancellation')
            ->assertSet('confirmingCancellation', true)
            ->assertSet('cancellationReason', '');
    }

    public function test_cancel_rejects_short_reason(): void
    {
        Http::fake([
            '*/api/v2/web/sales/123' => Http::response([
                'success' => true,
                'data' => ['id' => 123, 'folio' => 'V-123', 'status' => 'completed'],
            ], 200),
        ]);

        $this->actingAs(new GenericUser(['id' => 1]));
        $this->session(['permissions' => ['sales.cancel']]);

        Livewire::test(Show::class, ['saleId' => 123])
            ->call('confirmCancellation')
            ->set('cancellationReason', 'Corto')
            ->call('cancelSale')
            ->assertHasErrors(['cancellationReason']);
    }

    public function test_sale_api_error_sets_error_message(): void
    {
        Http::fake([
            '*/api/v2/web/sales/999*' => Http::response([
                'code' => 'NOT_FOUND',
                'message' => 'Venta no encontrada.',
            ], 404),
        ]);

        Livewire::test(Show::class, ['saleId' => 999])
            ->assertSet('errorMessage', 'Ocurrió un error en el servicio.');
    }
}
