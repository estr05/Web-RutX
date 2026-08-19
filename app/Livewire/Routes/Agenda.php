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

    public bool $loading = false;

    public ?string $errorMessage = null;

    public ?string $traceId = null;

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

        $this->loading = false;
    }

    /**
     * Aplica un lote de cambios desde el frontend (Drag&Drop).
     *
     * @param  array<int, array<string, mixed>>  $changes
     */
    public function applyBatch(array $changes): void
    {
        $this->loading = true;
        $this->errorMessage = null;

        $payload = [
            'schedule_version' => $this->scheduleVersion,
            'assignments' => $changes,
        ];

        // 1. Validar request
        $validator = Validator::make($payload, (new BatchAssignmentRequest)->rules());

        if ($validator->fails()) {
            $this->dispatch('agenda:notify', type: 'error', message: 'Los movimientos enviados son inválidos.');
            $this->loading = false;

            return;
        }

        // 2. Generar Idempotency-Key
        $idempotencyKey = 'batch-'.Str::uuid()->toString();

        // 3. Mutación
        $response = app(AgendaService::class)->assignBatch($payload, $idempotencyKey);
        $this->traceId = $response['trace_id'] ?? null;

        if (! $response['success']) {
            if (($response['code'] ?? '') === 'SCHEDULE_VERSION_CONFLICT') {
                $this->dispatch('agenda:notify', type: 'warning', message: 'La agenda fue modificada por otro usuario. Recargando el tablero...');
                $this->loadBoard();
            } else {
                $this->dispatch('agenda:notify', type: 'error', message: $response['message'] ?? 'Error al guardar los cambios.');
                $this->loading = false;
            }

            return;
        }

        // Éxito: actualiza version y despacha evento
        $this->scheduleVersion = $response['data']['schedule_version'] ?? $this->scheduleVersion;
        $this->dispatch('agenda:notify', type: 'success', message: 'Cambios guardados correctamente.');

        // Recarga para reflejar estado real (en el sprint final esto podría hacerse optimista,
        // pero por la guideline §3.2 de "veracidad", recargamos)
        $this->loadBoard();
    }

    public function render(): View
    {
        return view('livewire.routes.agenda');
    }
}
