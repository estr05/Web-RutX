<?php

declare(strict_types=1);

namespace App\Livewire\Sales;

use App\Services\CancellationRequestService;
use App\Services\SalesService;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    #[Locked]
    public int $saleId;

    #[Locked]
    public array $sale = [];

    public bool $loading = false;

    public ?string $errorMessage = null;

    // Modal state
    public bool $confirmingCancellation = false;

    public string $cancellationReason = '';

    #[Locked]
    public string $idempotencyKey = '';

    public function mount(int $saleId): void
    {
        $this->saleId = $saleId;
        $this->idempotencyKey = (string) Str::uuid();
        $this->loadSale();
    }

    public function loadSale(): void
    {
        $this->loading = true;

        $response = app(SalesService::class)->show($this->saleId);

        if (! $response['success']) {
            $this->errorMessage = $response['message'] ?? 'Error al cargar el detalle de la transacción.';
        } else {
            $this->sale = $response['data'] ?? [];
        }

        $this->loading = false;
    }

    public function confirmCancellation(): void
    {
        abort_unless(in_array('sales.cancel', session('permissions', [])), 403, 'No autorizado para cancelar ventas.');
        $this->confirmingCancellation = true;
        $this->cancellationReason = '';
        $this->resetValidation();
    }

    public function cancelSale(): void
    {
        abort_unless(in_array('sales.cancel', session('permissions', [])), 403, 'No autorizado para cancelar ventas.');

        $this->loading = true;

        $validated = $this->validate([
            'cancellationReason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $response = app(CancellationRequestService::class)->create(
            $this->saleId,
            ['reason' => $validated['cancellationReason']],
            'cancel-'.$this->idempotencyKey
        );

        if (! $response['success']) {
            $this->dispatch('rutx:feedback', type: 'error', message: $response['message'] ?? 'Error al procesar la cancelación.');
            $this->loading = false;

            return;
        }

        $this->confirmingCancellation = false;
        $this->idempotencyKey = (string) Str::uuid(); // Rotar token tras éxito
        $this->dispatch('rutx:feedback', type: 'success', message: 'Solicitud de cancelación enviada correctamente.');

        // Recargar detalle para reflejar cambio de estado (ej: pending_cancellation)
        $this->loadSale();
    }

    public function render()
    {
        return view('livewire.sales.show');
    }
}
