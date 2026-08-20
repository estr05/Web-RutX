<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Routes;

use App\Livewire\Routes\Agenda;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AgendaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.api_web', [
            'base_url' => 'https://sincronizador.example.test/api/v2/web',
            'timeout' => 10,
            'connect_timeout' => 5,
            'verify_tls' => true,
        ]);

        $this->session(['api_token' => 'jwt-test-token']);
    }

    public function test_renders_successfully(): void
    {
        Http::fake([
            '*/api/v2/web/agendas*' => Http::response([
                'success' => true,
                'data' => [
                    'schedule_version' => 1,
                    'days' => [],
                    'options' => ['zones' => [], 'routes' => []],
                ],
            ], 200),
        ]);

        Livewire::test(Agenda::class)
            ->assertStatus(200)
            ->assertSet('scheduleVersion', 1);
    }

    public function test_displays_error_on_api_failure(): void
    {
        Http::fake([
            '*/api/v2/web/agendas*' => Http::response([
                'code' => 'SERVER_ERROR',
                'message' => 'Fallo interno.',
            ], 500),
        ]);

        Livewire::test(Agenda::class)
            ->assertStatus(200)
            ->assertSet('errorMessage', 'No se pudo conectar con el servicio.');
    }

    public function test_queue_assignment_adds_to_pending_batch(): void
    {
        Http::fake([
            '*/api/v2/web/agendas*' => Http::response([
                'success' => true,
                'data' => [
                    'schedule_version' => 1,
                    'days' => [],
                    'options' => ['zones' => [], 'routes' => []],
                ],
            ], 200),
        ]);

        $this->actingAs(new GenericUser(['id' => 1]));
        $this->session(['permissions' => ['agendas.assign']]);

        Livewire::test(Agenda::class)
            ->call('queueAssignment', 123, 3572, '2026-08-18')
            ->assertSet('pendingBatch', [
                ['customer_id' => 123, 'seller_id' => 3572, 'agenda_date' => '2026-08-18', 'action' => 'assign'],
            ]);
    }

    public function test_remove_pending_assignment_removes_from_batch(): void
    {
        Http::fake([
            '*/api/v2/web/agendas*' => Http::response([
                'success' => true,
                'data' => [
                    'schedule_version' => 1,
                    'days' => [],
                    'options' => ['zones' => [], 'routes' => []],
                ],
            ], 200),
        ]);

        $this->actingAs(new GenericUser(['id' => 1]));
        $this->session(['permissions' => ['agendas.assign']]);

        Livewire::test(Agenda::class)
            ->call('queueAssignment', 123, 3572, '2026-08-18')
            ->call('queueAssignment', 456, 3573, '2026-08-19')
            ->assertSet('pendingBatch', [
                ['customer_id' => 123, 'seller_id' => 3572, 'agenda_date' => '2026-08-18', 'action' => 'assign'],
                ['customer_id' => 456, 'seller_id' => 3573, 'agenda_date' => '2026-08-19', 'action' => 'assign'],
            ])
            ->call('removePendingAssignment', 0)
            ->assertSet('pendingBatch', [
                ['customer_id' => 456, 'seller_id' => 3573, 'agenda_date' => '2026-08-19', 'action' => 'assign'],
            ]);
    }

    public function test_queue_removal_adds_remove_action(): void
    {
        Http::fake([
            '*/api/v2/web/agendas*' => Http::response([
                'success' => true,
                'data' => [
                    'schedule_version' => 1,
                    'days' => [],
                    'options' => ['zones' => [], 'routes' => []],
                ],
            ], 200),
        ]);

        $this->actingAs(new GenericUser(['id' => 1]));
        $this->session(['permissions' => ['agendas.assign']]);

        Livewire::test(Agenda::class)
            ->call('queueRemoval', 789)
            ->assertSet('pendingBatch', [
                ['customer_id' => 789, 'seller_id' => null, 'agenda_date' => null, 'action' => 'remove'],
            ]);
    }

    public function test_apply_batch_empty_returns_info_feedback(): void
    {
        Http::fake([
            '*/api/v2/web/agendas*' => Http::response([
                'success' => true,
                'data' => [
                    'schedule_version' => 1,
                    'days' => [],
                    'options' => ['zones' => [], 'routes' => []],
                ],
            ], 200),
        ]);

        $this->actingAs(new GenericUser(['id' => 1]));
        $this->session(['permissions' => ['agendas.assign']]);

        Livewire::test(Agenda::class)
            ->call('applyBatch')
            ->assertDispatched('rutx:feedback', type: 'info');
    }

    public function test_open_assign_modal_sets_state(): void
    {
        Http::fake([
            '*/api/v2/web/agendas*' => Http::response([
                'success' => true,
                'data' => [
                    'schedule_version' => 1,
                    'days' => [],
                    'options' => ['zones' => [], 'routes' => []],
                ],
            ], 200),
        ]);

        Livewire::test(Agenda::class)
            ->call('openAssignModal', 123)
            ->assertSet('showAssignModal', true)
            ->assertSet('assignCustomerId', '123')
            ->assertSet('assignSellerId', null)
            ->assertSet('assignDate', null);
    }

    public function test_submit_assignment_queues_and_closes_modal(): void
    {
        Http::fake([
            '*/api/v2/web/agendas/unassigned-customers*' => Http::response([
                'success' => true,
                'data' => [],
            ], 200),
            '*/api/v2/web/agendas*' => Http::response([
                'success' => true,
                'data' => [
                    'schedule_version' => 1,
                    'days' => [
                        ['date' => '2026-08-18', 'weekday' => 'Martes', 'sellers' => [['seller_id' => 3572, 'seller_name' => 'RUTA01']]],
                    ],
                    'options' => ['zones' => [], 'routes' => []],
                ],
            ], 200),
        ]);

        $this->actingAs(new GenericUser(['id' => 1]));
        $this->session(['permissions' => ['agendas.assign']]);

        Livewire::test(Agenda::class)
            ->call('openAssignModal', 123)
            ->set('assignSellerId', '3572')
            ->set('assignDate', '2026-08-18')
            ->call('submitAssignment')
            ->assertSet('showAssignModal', false)
            ->assertSet('pendingBatch', [
                ['customer_id' => 123, 'seller_id' => 3572, 'agenda_date' => '2026-08-18', 'action' => 'assign'],
            ]);
    }

    public function test_submit_assignment_rejects_empty_string_seller_or_date(): void
    {
        Http::fake([
            '*/api/v2/web/agendas*' => Http::response([
                'success' => true,
                'data' => [
                    'schedule_version' => 1,
                    'days' => [],
                    'options' => ['zones' => [], 'routes' => []],
                ],
            ], 200),
        ]);

        $this->actingAs(new GenericUser(['id' => 1]));
        $this->session(['permissions' => ['agendas.assign']]);

        Livewire::test(Agenda::class)
            ->call('openAssignModal', 123)
            ->set('assignSellerId', '')
            ->set('assignDate', '')
            ->call('submitAssignment')
            ->assertSet('showAssignModal', true)
            ->assertDispatched('rutx:feedback', type: 'warning');
    }

    public function test_available_sellers_derived_from_days(): void
    {
        Http::fake([
            '*/api/v2/web/agendas*' => Http::response([
                'success' => true,
                'data' => [
                    'schedule_version' => 1,
                    'days' => [
                        [
                            'date' => '2026-08-18',
                            'sellers' => [
                                ['seller_id' => 3572, 'seller_name' => 'RUTA01'],
                            ],
                        ],
                        [
                            'date' => '2026-08-19',
                            'sellers' => [
                                ['seller_id' => 3572, 'seller_name' => 'RUTA01'],
                                ['seller_id' => 3573, 'seller_name' => 'RUTA02'],
                            ],
                        ],
                    ],
                    'options' => ['zones' => [], 'routes' => []],
                ],
            ], 200),
        ]);

        Livewire::test(Agenda::class)
            ->assertSet('availableSellers', [
                ['seller_id' => 3572, 'seller_name' => 'RUTA01'],
                ['seller_id' => 3573, 'seller_name' => 'RUTA02'],
            ]);
    }

    public function test_load_unassigned_propagates_zone_filter(): void
    {
        Http::fake([
            '*/api/v2/web/agendas/unassigned-customers*' => Http::response([
                'success' => true,
                'data' => [['customer_id' => 10, 'name' => 'Cliente Z1']],
            ], 200),
            '*/api/v2/web/agendas*' => Http::response([
                'success' => true,
                'data' => [
                    'schedule_version' => 1,
                    'days' => [],
                    'options' => ['zones' => [], 'routes' => []],
                ],
            ], 200),
        ]);

        Livewire::test(Agenda::class)
            ->set('filters.zone_id', '5')
            ->assertSet('unassignedCustomers', [['customer_id' => 10, 'name' => 'Cliente Z1']]);
    }

    public function test_apply_batch_success_resets_state(): void
    {
        Http::fake([
            '*/api/v2/web/agendas/unassigned-customers*' => Http::response([
                'success' => true,
                'data' => [],
            ], 200),
            '*/api/v2/web/agendas/assignments:batch' => Http::response([
                'success' => true,
                'data' => ['schedule_version' => 2],
            ], 200),
            '*/api/v2/web/agendas*' => Http::response([
                'success' => true,
                'data' => [
                    'schedule_version' => 2,
                    'days' => [],
                    'options' => ['zones' => [], 'routes' => []],
                ],
            ], 200),
        ]);

        $this->actingAs(new GenericUser(['id' => 1]));
        $this->session(['permissions' => ['agendas.assign']]);

        Livewire::test(Agenda::class)
            ->call('queueAssignment', 123, 3572, '2026-08-18')
            ->assertSet('pendingBatch', [
                ['customer_id' => 123, 'seller_id' => 3572, 'agenda_date' => '2026-08-18', 'action' => 'assign'],
            ])
            ->call('applyBatch')
            ->assertSet('pendingBatch', [])
            ->assertSet('scheduleVersion', 2)
            ->assertSet('idempotencyKey', '')
            ->assertDispatched('rutx:feedback', type: 'success');
    }

    public function test_apply_batch_payload_normalizes_assign(): void
    {
        $capturedPayload = null;

        Http::fake([
            '*/api/v2/web/agendas/unassigned-customers*' => Http::response([
                'success' => true,
                'data' => [],
            ], 200),
            '*/api/v2/web/agendas/assignments:batch' => function (Request $request) use (&$capturedPayload) {
                $capturedPayload = $request->data();

                return Http::response([
                    'success' => true,
                    'data' => ['schedule_version' => 2],
                ], 200);
            },
            '*/api/v2/web/agendas*' => Http::response([
                'success' => true,
                'data' => [
                    'schedule_version' => 2,
                    'days' => [],
                    'options' => ['zones' => [], 'routes' => []],
                ],
            ], 200),
        ]);

        $this->actingAs(new GenericUser(['id' => 1]));
        $this->session(['permissions' => ['agendas.assign']]);

        Livewire::test(Agenda::class)
            ->call('queueAssignment', 123, 3572, '2026-08-18')
            ->call('applyBatch');

        $this->assertNotNull($capturedPayload);
        $this->assertArrayHasKey('schedule_version', $capturedPayload);
        $this->assertArrayHasKey('assignments', $capturedPayload);
        $this->assertCount(1, $capturedPayload['assignments']);
        $this->assertEquals(123, $capturedPayload['assignments'][0]['customer_id']);
        $this->assertEquals('assign', $capturedPayload['assignments'][0]['action']);
        $this->assertEquals(3572, $capturedPayload['assignments'][0]['seller_id']);
        $this->assertEquals('2026-08-18', $capturedPayload['assignments'][0]['agenda_date']);
    }

    public function test_apply_batch_reuses_idempotency_key_on_conflict(): void
    {
        Http::fake([
            '*/api/v2/web/agendas/unassigned-customers*' => Http::response([
                'success' => true,
                'data' => [],
            ], 200),
            '*/api/v2/web/agendas/assignments:batch' => Http::response([
                'success' => false,
                'code' => 'SCHEDULE_VERSION_CONFLICT',
                'message' => 'Conflicto de versión.',
            ], 409),
            '*/api/v2/web/agendas*' => Http::response([
                'success' => true,
                'data' => [
                    'schedule_version' => 5,
                    'days' => [],
                    'options' => ['zones' => [], 'routes' => []],
                ],
            ], 200),
        ]);

        $this->actingAs(new GenericUser(['id' => 1]));
        $this->session(['permissions' => ['agendas.assign']]);

        Livewire::test(Agenda::class)
            ->call('queueAssignment', 123, 3572, '2026-08-18')
            ->call('applyBatch')
            ->assertSet('scheduleVersion', 5)
            ->assertSet('pendingBatch', [
                ['customer_id' => 123, 'seller_id' => 3572, 'agenda_date' => '2026-08-18', 'action' => 'assign'],
            ])
            ->assertSet('idempotencyKey', fn ($key) => str_starts_with($key, 'batch-'))
            ->assertDispatched('rutx:feedback', type: 'warning');
    }

    public function test_apply_batch_remote_error_dispatches_error(): void
    {
        Http::fake([
            '*/api/v2/web/agendas/unassigned-customers*' => Http::response([
                'success' => true,
                'data' => [],
            ], 200),
            '*/api/v2/web/agendas/assignments:batch' => Http::response([
                'success' => false,
                'message' => 'Error interno del servidor.',
            ], 500),
            '*/api/v2/web/agendas*' => Http::response([
                'success' => true,
                'data' => [
                    'schedule_version' => 1,
                    'days' => [],
                    'options' => ['zones' => [], 'routes' => []],
                ],
            ], 200),
        ]);

        $this->actingAs(new GenericUser(['id' => 1]));
        $this->session(['permissions' => ['agendas.assign']]);

        Livewire::test(Agenda::class)
            ->call('queueAssignment', 123, 3572, '2026-08-18')
            ->call('applyBatch')
            ->assertDispatched('rutx:feedback', type: 'error')
            ->assertSet('pendingBatch', [
                ['customer_id' => 123, 'seller_id' => 3572, 'agenda_date' => '2026-08-18', 'action' => 'assign'],
            ]);
    }

    public function test_apply_batch_without_permission_throws_403(): void
    {
        Http::fake([
            '*/api/v2/web/agendas*' => Http::response([
                'success' => true,
                'data' => [
                    'schedule_version' => 1,
                    'days' => [],
                    'options' => ['zones' => [], 'routes' => []],
                ],
            ], 200),
        ]);

        Livewire::test(Agenda::class)
            ->call('applyBatch')
            ->assertForbidden();
    }
}
