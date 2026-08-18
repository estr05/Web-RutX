<?php

declare(strict_types=1);

namespace App\Livewire\Notifications;

use App\Http\Requests\NotificationFilterRequest;
use App\Http\Requests\SendNotificationRequest;
use App\Services\NotificationsService;
use App\Support\Feedback;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Notificaciones — bandeja y emisión de avisos (notifications.index).
 *
 * Estado de la bandeja (filtros status/target_type, paginación), contador
 * local de activos y comando de emisión SIN reintento automático (plan §5.3):
 * la clave de idempotencia se conserva durante el intento y solo se invalida
 * después de una respuesta exitosa — un doble clic no produce dos avisos y un
 * 409 se muestra como conflicto funcional.
 */
class Index extends Component
{
    public string $status = '';

    public string $targetType = '';

    public int $page = 1;

    public int $perPage = 25;

    /** Formulario de emisión. */
    public string $targetTypeSend = 'seller';

    public string $targetIdsText = '';

    public string $title = '';

    public string $body = '';

    public string $priority = 'normal';

    /** Clave de idempotencia del comando vigente; se invalida solo tras éxito. */
    public string $idempotencyKey = '';

    /** Evita el doble clic mientras la emisión está en vuelo. */
    public bool $sending = false;

    protected $listeners = [
        'notifications:refresh' => 'refreshLocalCount',
    ];

    public function mount(): void
    {
        $this->idempotencyKey = Str::uuid()->toString();
    }

    public function render()
    {
        return view('modules.notifications._index-content');
    }

    /**
     * Resultado crudo de la bandeja (success, data, meta, code, message).
     */
    public function getResultProperty(): array
    {
        return app(NotificationsService::class)->list($this->filterPayload());
    }

    /**
     * Filas de la bandeja.
     */
    public function getRowsProperty(): array
    {
        return $this->result['success'] ? ($this->result['data'] ?? []) : [];
    }

    /**
     * Meta de paginación (page, per_page, total, last_page).
     */
    public function getMetaProperty(): array
    {
        return $this->result['success'] ? ($this->result['meta'] ?? []) : [];
    }

    /**
     * Mensaje de error funcional de la bandeja; null si hay éxito.
     */
    public function getErrorProperty(): ?string
    {
        if ($this->result['success']) {
            return null;
        }

        return (string) ($this->result['message'] ?? 'No se pudo conectar con el servicio.');
    }

    /**
     * Contador local de avisos activos (bandeja). La campana del topbar
     * mantiene su propio estado; este valor solo refresca la vista actual.
     */
    public function getActiveCountProperty(): int
    {
        return (int) ($this->result['meta']['total'] ?? 0);
    }

    public function consultar()
    {
        $validator = Validator::make($this->filterInput(), (new NotificationFilterRequest)->rules());

        if ($validator->fails()) {
            $this->dispatch('rutx:feedback', Feedback::error('Revisa los filtros de la bandeja.'));
            $this->setErrorBag($validator->errors());

            return;
        }

        $this->page = 1;
        $this->resetErrorBag();
    }

    public function irAPagina(int $page)
    {
        $lastPage = (int) ($this->meta['last_page'] ?? 1);

        if ($page < 1 || $page > $lastPage) {
            return;
        }

        $this->page = $page;
    }

    public function limpiar()
    {
        $this->reset('status', 'targetType', 'page');
    }

    /**
     * Emisión de avisos con idempotencia. Sin reintento automático: la clave
     * se conserva ante 409/error y solo se regenera tras una respuesta exitosa.
     */
    public function enviar()
    {
        if ($this->sending) {
            return;
        }

        $validator = Validator::make($this->sendInput(), (new SendNotificationRequest)->rules());

        if ($validator->fails()) {
            $this->dispatch('rutx:feedback', Feedback::error('Revisa los datos del aviso.'));
            $this->setErrorBag($validator->errors());

            return;
        }

        $this->sending = true;

        try {
            $result = app(NotificationsService::class)->send(
                (new SendNotificationRequest)->setValidator($validator)->toApiPayload(),
                $this->idempotencyKey,
            );
        } finally {
            $this->sending = false;
        }

        if ($result['success']) {
            $this->dispatch('rutx:feedback', Feedback::success('Aviso emitido correctamente.'));
            $this->dispatch('notifications:refresh');
            $this->reset('targetIdsText', 'title', 'body');
            $this->idempotencyKey = Str::uuid()->toString();
            $this->consultar();

            return;
        }

        if (($result['code'] ?? null) === 'IDEMPOTENCY_CONFLICT') {
            $this->dispatch('rutx:feedback', Feedback::warning(
                'Este aviso ya fue emitido con la misma clave de idempotencia.',
                title: 'Conflicto de idempotencia',
            ));

            return;
        }

        $this->dispatch('rutx:feedback', Feedback::error(
            (string) ($result['message'] ?? 'No se pudo emitir el aviso.'),
            isRecoverable: true,
            retryEvent: 'enviar',
        ));
    }

    /**
     * Refresca el contador local cuando la campana detecta cambios.
     */
    public function refreshLocalCount(): void
    {
        $this->page = 1;
    }

    /**
     * Filtros de la bandeja en snake_case (forma del contrato v2).
     *
     * @return array<string, mixed>
     */
    private function filterInput(): array
    {
        return [
            'status' => $this->status,
            'target_type' => $this->targetType,
            'page' => $this->page,
            'per_page' => $this->perPage,
        ];
    }

    /**
     * Payload de la bandeja: solo campos validados y con valor.
     *
     * @return array<string, mixed>
     */
    private function filterPayload(): array
    {
        try {
            $validated = Validator::make($this->filterInput(), (new NotificationFilterRequest)->rules())->validated();
        } catch (ValidationException) {
            return ['page' => 1, 'per_page' => $this->perPage];
        }

        return collect($validated)
            ->reject(fn (mixed $value): bool => is_null($value) || $value === '')
            ->all();
    }

    /**
     * Entrada del formulario de emisión (snake_case, forma del contrato v2).
     *
     * @return array<string, mixed>
     */
    private function sendInput(): array
    {
        return [
            'target_type' => $this->targetTypeSend,
            'target_ids' => collect(explode(',', $this->targetIdsText))
                ->map(fn (string $id): string => trim($id))
                ->filter(fn (string $id): bool => $id !== '')
                ->map(fn (string $id): int => (int) $id)
                ->values()
                ->all(),
            'title' => $this->title,
            'body' => $this->body,
            'priority' => $this->priority,
        ];
    }
}
