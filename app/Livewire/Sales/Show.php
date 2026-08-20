<?php

declare(strict_types=1);

namespace App\Livewire\Sales;

use App\Http\Requests\CancellationCreateRequest;
use App\Services\CancellationRequestService;
use App\Services\SalesService;
use Illuminate\Support\Facades\Validator;
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
        $this->authorize('sales.cancel');
        $this->confirmingCancellation = true;
        $this->cancellationReason = '';
        $this->resetValidation();
    }

    public function cancelSale(): void
    {
        $this->authorize('sales.cancel');

        $this->loading = true;

        $validator = Validator::make(
            ['reason' => $this->cancellationReason],
            (new CancellationCreateRequest)->rules()
        );

        if ($validator->fails()) {
            $this->setErrorBag($validator->errors()->toArray());
            $this->addError('cancellationReason', $validator->errors()->first('reason'));
            $this->loading = false;

            return;
        }

        $response = app(CancellationRequestService::class)->create(
            $this->saleId,
            $validator->validated(),
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
