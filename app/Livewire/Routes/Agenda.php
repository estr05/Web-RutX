<?php

declare(strict_types=1);

namespace App\Livewire\Routes;

use App\Http\Requests\AgendaQueryRequest;
use App\Http\Requests\BatchAssignmentRequest;
use App\Services\AgendaService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Componente Livewire principal para la gestión de Agenda (Sprint 5).
 *
 * Sigue las Guidelines del proyecto:
 * 1. Usa Form Requests (AgendaQueryRequest, BatchAssignmentRequest) para validación.
 * 2. Mantiene un estado mínimo ($filters y el resultado crudo serializable).
 * 3. Delega la lógica HTTP en AgendaService.
 * 4. Envía Idempotency-Key en mutaciones.
 */
class Agenda extends Component
{
    /** @var array<string, mixed> */
    public array $filters = [
        'zone_id' => null,
        'route_id' => null,
        'date_from' => null,
        'date_to' => null,
    ];

    #[Locked]
    public int $scheduleVersion = 0;

    /** @var array<int, mixed> */
    #[Locked]
    public array $days = [];

    /** @var array<string, mixed> */
    #[Locked]
    public array $options = ['zones' => [], 'routes' => []];

    #[Locked]
    public array $unassignedCustomers = [];

    public bool $loading = false;

    public ?string $errorMessage = null;

    public ?string $traceId = null;

    // Assignment modal state
    public bool $showAssignModal = false;

    public ?string $assignCustomerId = null;

    public ?string $assignSellerId = null;

    public ?string $assignDate = null;

    public function mount(): void
    {
        // Por defecto: semana actual (Lunes a Domingo)
        $this->filters['date_from'] = Carbon::now()->startOfWeek()->format('Y-m-d');
        $this->filters['date_to'] = Carbon::now()->endOfWeek()->format('Y-m-d');

        $this->loadBoard();
    }

    public function updatedFilters(): void
    {
        $this->loadBoard();
    }

    public function loadBoard(): void
    {
        $this->loading = true;
        $this->errorMessage = null;

        // 1. Validar filtros (Form Request pattern en Livewire)
        $validator = Validator::make($this->filters, (new AgendaQueryRequest)->rules());
        if ($validator->fails()) {
            $this->errorMessage = 'Filtros inválidos.';
            $this->loading = false;

            return;
        }

        $apiFilters = array_filter($validator->validated(), fn ($v) => $v !== null && $v !== '');

        // 2. Fetch via Service
        $response = app(AgendaService::class)->board($apiFilters);
        $this->traceId = $response['trace_id'] ?? null;

        if (! $response['success']) {
            $this->errorMessage = $response['message'] ?? 'Error al cargar la agenda.';
            $this->loading = false;

            return;
        }

        // 3. Hydrate state
        $data = $response['data'] ?? [];
        $this->scheduleVersion = $data['schedule_version'] ?? 0;
        $this->days = $data['days'] ?? [];
        $this->options = $data['options'] ?? $this->options;

        $this->loadUnassigned();

        $this->loading = false;
    }

    public function loadUnassigned(): void
    {
        $response = app(AgendaService::class)->unassignedCustomers([]);
        if ($response['success']) {
            $this->unassignedCustomers = $response['data'] ?? [];
        }
    }

    public function openAssignModal(string $customerId): void
    {
        $this->assignCustomerId = (string) $customerId;
        $this->assignSellerId = null;
        $this->assignDate = null;
        $this->showAssignModal = true;
    }

    public function closeAssignModal(): void
    {
        $this->showAssignModal = false;
        $this->assignCustomerId = null;
        $this->assignSellerId = null;
        $this->assignDate = null;
    }

    public function submitAssignment(): void
    {
        $this->authorize('agendas.assign');

        if (empty($this->assignSellerId) || empty($this->assignDate)) {
            $this->dispatch('rutx:feedback', type: 'warning', message: 'Seleccione vendedor y fecha antes de confirmar.');
            return;
        }

        $this->queueAssignment(
            (int) $this->assignCustomerId,
            (int) $this->assignSellerId,
            $this->assignDate,
        );

        $this->dispatch('rutx:feedback', type: 'info', message: 'Movimiento en cola. Presione Guardar para aplicar.');
        $this->closeAssignModal();
    }

    public function queueAssignment(int $customerId, int $sellerId, string $agendaDate): void
    {
        $this->authorize('agendas.assign');

        $this->pendingBatch[] = [
            'customer_id' => $customerId,
            'seller_id' => $sellerId,
            'agenda_date' => $agendaDate,
            'action' => 'assign',
        ];
    }

    public function queueRemoval(int $customerId): void
    {
        $this->authorize('agendas.assign');

        $this->pendingBatch[] = [
            'customer_id' => $customerId,
            'seller_id' => null,
            'agenda_date' => null,
            'action' => 'remove',
        ];

        $this->dispatch('rutx:feedback', type: 'info', message: 'Movimiento en cola. Presione Guardar para aplicar.');
    }

    public function removePendingAssignment(int $index): void
    {
        $this->authorize('agendas.assign');

        array_splice($this->pendingBatch, $index, 1);
    }

    /**
     * Vendedores únicos derivados de los días del tablero.
     * No se usa options.routes — la fuente son los días (contrato §11).
     *
     * @return array<int, array{seller_id: int, seller_name: string}>
     */
    public function getAvailableSellersProperty(): array
    {
        $seen = [];
        $sellers = [];

        foreach ($this->days as $day) {
            foreach ($day['sellers'] ?? [] as $seller) {
                $id = (int) $seller['seller_id'];
                if (! isset($seen[$id])) {
                    $seen[$id] = true;
                    $sellers[] = [
                        'seller_id' => $id,
                        'seller_name' => $seller['seller_name'] ?? $seller['name'] ?? "Vendedor #{$id}",
                    ];
                }
            }
        }

        return $sellers;
    }

    /**
     * Fechas únicas de los días del tablero para el select del modal.
     *
     * @return array<int, array{date: string, label: string}>
     */
    public function getAvailableDatesProperty(): array
    {
        return array_map(fn ($day) => [
            'date' => $day['date'],
            'label' => ($day['weekday'] ?? '').' '.\Carbon\Carbon::parse($day['date'])->format('d/m/Y'),
        ], $this->days);
    }

    #[Locked]
    public string $idempotencyKey = '';

    public array $pendingBatch = [];

    public function applyBatch(array $changes = []): void
    {
        $this->authorize('agendas.assign');

        $this->loading = true;
        $this->errorMessage = null;

        // Recupera el batch en caso de reintento, o inicializa con los cambios nuevos
        $this->pendingBatch = ! empty($this->pendingBatch) && empty($changes) ? $this->pendingBatch : $changes;

        if (empty($this->pendingBatch)) {
            $this->dispatch('rutx:feedback', type: 'info', message: 'No hay movimientos pendientes para guardar.');
            $this->loading = false;

            return;
        }

        $payload = [
            'schedule_version' => $this->scheduleVersion,
            'assignments' => $this->pendingBatch,
        ];

        // 1. Validar request
        $validator = Validator::make($payload, (new BatchAssignmentRequest)->rules());

        if ($validator->fails()) {
            $this->dispatch('rutx:feedback', type: 'error', message: 'Los movimientos enviados son inválidos.');
            $this->loading = false;

            return;
        }

        // 2. Generar o reutilizar Idempotency-Key
        if (empty($this->idempotencyKey)) {
            $this->idempotencyKey = 'batch-'.Str::uuid()->toString();
        }

        // 3. Mutación
        $apiPayload = BatchAssignmentRequest::formatApiPayload($validator->validated());
        $response = app(AgendaService::class)->assignBatch($apiPayload, $this->idempotencyKey);
        $this->traceId = $response['trace_id'] ?? null;

        if (! $response['success']) {
            if (($response['code'] ?? '') === 'SCHEDULE_VERSION_CONFLICT') {
                $this->dispatch('rutx:feedback', type: 'warning', message: 'Conflicto de versión. El tablero ha sido recargado. Presione Guardar nuevamente para sobreescribir.');
                // Recargamos la versión real para que el próximo intento la envíe.
                $this->loadBoard();
            } else {
                $this->dispatch('rutx:feedback', type: 'error', message: $response['message'] ?? 'Error al guardar los cambios.');
                $this->loading = false;
            }

            return;
        }

        // Éxito: actualiza version, resetea estado pendiente e idempotencia
        $this->scheduleVersion = $response['data']['schedule_version'] ?? $this->scheduleVersion;
        $this->pendingBatch = [];
        $this->idempotencyKey = '';
        $this->dispatch('rutx:feedback', type: 'success', message: 'Cambios guardados correctamente.');

        // Recarga para reflejar estado real
        $this->loadBoard();
    }

    public function render(): View
    {
        return view('livewire.routes.agenda');
    }
}
