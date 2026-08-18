<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Notifications\Bell;
use App\Services\NotificationsService;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

/**
 * NotificationsBellTest
 *
 * Verifica la campana del topbar (Sprint 4 · Bloque C · C4):
 * - Estado inicial desconocido (null) sin consultar: NO se muestra cero.
 * - Con respuesta exitosa muestra el conteo de activos.
 * - Ante error de red conserva el último valor conocido y marca $failed:
 *   “desconocido” nunca se convierte en cero (checklist §9).
 * - El polling declarado es de 30 segundos.
 */
class NotificationsBellTest extends TestCase
{
    public function test_initial_state_is_unknown_not_zero(): void
    {
        $service = Mockery::mock(NotificationsService::class);
        $service->shouldNotReceive('countActive');
        $this->app->instance(NotificationsService::class, $service);

        Livewire::test(Bell::class)
            ->assertSet('count', null)
            ->assertSet('failed', false)
            ->assertSee('wire:poll.30s', false);
    }

    public function test_successful_refresh_sets_active_count(): void
    {
        $service = Mockery::mock(NotificationsService::class);
        $service->shouldReceive('countActive')->once()->andReturn([
            'success' => true,
            'data' => ['active_count' => 7],
        ]);
        $this->app->instance(NotificationsService::class, $service);

        Livewire::test(Bell::class)
            ->call('refresh')
            ->assertSet('count', 7)
            ->assertSet('failed', false);
    }

    public function test_zero_active_is_a_legit_value_from_the_api(): void
    {
        $service = Mockery::mock(NotificationsService::class);
        $service->shouldReceive('countActive')->once()->andReturn([
            'success' => true,
            'data' => ['active_count' => 0],
        ]);
        $this->app->instance(NotificationsService::class, $service);

        Livewire::test(Bell::class)
            ->call('refresh')
            ->assertSet('count', 0)
            ->assertSet('failed', false);
    }

    public function test_network_error_keeps_last_count_and_marks_failed(): void
    {
        $service = Mockery::mock(NotificationsService::class);
        $service->shouldReceive('countActive')->twice()->andReturn(
            ['success' => true, 'data' => ['active_count' => 5]],
            ['success' => false, 'code' => 'API_UNAVAILABLE', 'message' => 'No se pudo conectar con el servicio.'],
        );
        $this->app->instance(NotificationsService::class, $service);

        Livewire::test(Bell::class)
            ->call('refresh')
            ->assertSet('count', 5)
            ->call('refresh')
            ->assertSet('count', 5)
            ->assertSet('failed', true);
    }

    public function test_unknown_state_after_failure_is_not_zero(): void
    {
        $service = Mockery::mock(NotificationsService::class);
        $service->shouldReceive('countActive')->once()->andReturn([
            'success' => false,
            'code' => 'API_UNAVAILABLE',
            'message' => 'No se pudo conectar con el servicio.',
        ]);
        $this->app->instance(NotificationsService::class, $service);

        Livewire::test(Bell::class)
            ->call('refresh')
            ->assertSet('count', null)
            ->assertSet('failed', true);
    }
}
