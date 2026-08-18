<?php

declare(strict_types=1);

namespace App\Livewire\Notifications;

use App\Services\NotificationsService;
use Livewire\Component;

/**
 * Notificaciones — campana del topbar (utilidad, no módulo duplicado).
 *
 * Contador de avisos activos con polling de 30 segundos (guidelines §2.3).
 * Estados honestos (checklist §9): éxito (con/sin avisos), desconectado y
 * error. Ante un fallo de red la campana NO convierte “desconocido” en cero:
 * conserva el último valor conocido y marca el estado de error.
 */
class Bell extends Component
{
    /** Intervalo de polling en segundos: compromiso de 30 s (plan §4.3 C4). */
    public int $pollInterval = 30;

    /** Conteo activo; null = aún sin respuesta (desconocido, no cero). */
    public ?int $count = null;

    /** Error de red durante el último polling; no altera $count. */
    public bool $failed = false;

    protected $listeners = [
        'notifications:refresh' => 'refresh',
    ];

    public function render()
    {
        return view('components.notification-bell');
    }

    /**
     * Actualiza el contador desde GET /notifications/count. El error de red se
     * refleja en $failed sin tocar $count (desconocido ≠ cero).
     */
    public function refresh(): void
    {
        $result = app(NotificationsService::class)->countActive();

        if (! $result['success']) {
            $this->failed = true;

            return;
        }

        $this->count = (int) ($result['data']['active_count'] ?? 0);
        $this->failed = false;
    }
}
