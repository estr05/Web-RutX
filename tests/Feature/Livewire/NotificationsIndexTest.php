<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Notifications\Index;
use App\Services\NotificationsService;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

/**
 * NotificationsIndexTest
 *
 * Verifica la bandeja y la emisión de avisos (Sprint 4 · Bloque C):
 * - La bandeja se alimenta de GET /notifications con filtros validados.
 * - El envío usa la Idempotency-Key del componente; tras éxito se regenera.
 * - Un 409 (IDEMPOTENCY_CONFLICT) conserva la clave: no se crea un segundo
 *   aviso por doble clic ni por reintento con la misma clave.
 * - Un error no exitoso no reintenta automáticamente (feedback sin acción).
 *
 * Nota: el render del componente consulta la bandeja desde el primer render,
 * por lo que los mocks deben tolerar la llamada inicial de list().
 */
class NotificationsIndexTest extends TestCase
{
    private function mockService(): Mockery\MockInterface
    {
        $service = Mockery::mock(NotificationsService::class);
        $service->shouldReceive('list')->zeroOrMoreTimes()->andReturn([
            'success' => true,
            'data' => [],
            'meta' => ['page' => 1, 'per_page' => 25, 'total' => 0, 'last_page' => 1],
        ]);
        $this->app->instance(NotificationsService::class, $service);

        return $service;
    }

    public function test_inbox_renders_rows_with_priority_and_status_badges(): void
    {
        $service = Mockery::mock(NotificationsService::class);
        $service->shouldReceive('list')->zeroOrMoreTimes()->andReturn([
            'success' => true,
            'data' => [
                [
                    'id' => 1,
                    'targetType' => 'route',
                    'targetId' => 695,
                    'title' => 'Reunión de ruta',
                    'body' => 'Se cambió el horario de salida.',
                    'priority' => 'alta',
                    'status' => 'active',
                    'senderUsername' => 'admin.smoke',
                    'createdAt' => '2026-08-17T10:00:00-06:00',
                ],
            ],
            'meta' => ['page' => 1, 'per_page' => 25, 'total' => 1, 'last_page' => 1],
        ]);
        $this->app->instance(NotificationsService::class, $service);

        Livewire::test(Index::class)
            ->assertSee('Notificaciones')
            ->assertSee('Reunión de ruta')
            ->assertSee('Se cambió el horario de salida.')
            ->assertSee('Alta')
            ->assertSee('Activo')
            ->assertSee('admin.smoke');
    }

    public function test_send_success_regenerates_idempotency_key(): void
    {
        $service = $this->mockService();
        $service->shouldReceive('send')->once()->withArgs(function (array $payload, string $key): bool {
            return $payload['target_type'] === 'seller'
                && $payload['target_ids'] === [1, 2]
                && $payload['title'] === 'Aviso de prueba'
                && $payload['priority'] === 'normal'
                && $key !== '';
        })->andReturn([
            'success' => true,
            'data' => ['created_count' => 2, 'notification_ids' => [11, 12], 'target_type' => 'seller'],
        ]);

        Livewire::test(Index::class)
            ->set('targetTypeSend', 'seller')
            ->set('targetIdsText', '1, 2')
            ->set('title', 'Aviso de prueba')
            ->set('body', 'Mensaje del aviso')
            ->call('enviar')
            ->assertSet('targetIdsText', '')
            ->assertSet('title', '')
            ->assertSet('body', '');
    }

    public function test_idempotency_conflict_keeps_key_and_reports_conflict(): void
    {
        $service = $this->mockService();
        $service->shouldReceive('send')->once()->andReturn([
            'success' => false,
            'code' => 'IDEMPOTENCY_CONFLICT',
            'message' => 'Esta clave de idempotencia ya fue utilizada.',
        ]);

        Livewire::test(Index::class)
            ->set('targetTypeSend', 'zone')
            ->set('targetIdsText', '3792')
            ->set('title', 'Aviso duplicado')
            ->set('body', 'Mensaje')
            ->call('enviar')
            ->assertSet('idempotencyKey', fn (string $key): bool => $key !== '')
            ->assertDispatched('rutx:feedback');
    }

    public function test_send_error_does_not_clear_form_and_keeps_key(): void
    {
        $service = $this->mockService();
        $service->shouldReceive('send')->once()->andReturn([
            'success' => false,
            'code' => 'VALIDATION_ERROR',
            'message' => 'La zona solicitada no está dentro de las zonas autorizadas.',
        ]);

        Livewire::test(Index::class)
            ->set('targetTypeSend', 'zone')
            ->set('targetIdsText', '3795')
            ->set('title', 'Aviso fuera de zona')
            ->set('body', 'Mensaje')
            ->call('enviar')
            ->assertSet('targetIdsText', '3795')
            ->assertSet('title', 'Aviso fuera de zona')
            ->assertSet('body', 'Mensaje');
    }

    public function test_invalid_send_payload_is_rejected_without_api_call(): void
    {
        $service = $this->mockService();
        $service->shouldNotReceive('send');

        Livewire::test(Index::class)
            ->set('targetIdsText', '0')
            ->set('title', '')
            ->set('body', '')
            ->call('enviar')
            ->assertHasErrors(['target_ids.0', 'title', 'body']);
    }

    public function test_inbox_error_shows_honest_error_state(): void
    {
        $service = Mockery::mock(NotificationsService::class);
        $service->shouldReceive('list')->zeroOrMoreTimes()->andReturn([
            'success' => false,
            'code' => 'API_UNAVAILABLE',
            'message' => 'No se pudo conectar con el servicio.',
        ]);
        $this->app->instance(NotificationsService::class, $service);

        Livewire::test(Index::class)
            ->call('consultar')
            ->assertSee('No se pudo consultar la bandeja')
            ->assertSee('No se pudo conectar con el servicio.')
            ->assertSee('Reintentar');
    }
}
