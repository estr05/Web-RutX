<?php

declare(strict_types=1);

namespace Tests\Feature\Requests;

use App\Http\Requests\BatchAssignmentRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * BatchAssignmentRequestTest
 *
 * Valida las reglas del Form Request del batch de agenda (contrato v2 §6.2):
 * - schedule_version: requerido, entero >= 0.
 * - assignments: requerido, 1..50 elementos.
 * - assignments.*.customer_id: entero positivo requerido.
 * - assignments.*.action: assign | move | remove.
 * - assignments.*.seller_id: nullable, entero positivo.
 * - assignments.*.agenda_date: nullable, fecha ISO.
 * - toApiPayload(): construye desde validated(); normaliza remove (sin seller/date).
 *
 * Se usa Validator directamente para no necesitar una ruta HTTP completa.
 */
class BatchAssignmentRequestTest extends TestCase
{
    /** @param array<string, mixed> $data */
    private function validate(array $data): \Illuminate\Contracts\Validation\Validator
    {
        $request = new BatchAssignmentRequest;

        return Validator::make($data, $request->rules());
    }

    // -------------------------------------------------------------------------
    // Casos válidos
    // -------------------------------------------------------------------------

    public function test_valid_assign_action_passes(): void
    {
        $v = $this->validate([
            'schedule_version' => 42,
            'assignments' => [
                ['customer_id' => 123, 'action' => 'assign', 'seller_id' => 3572, 'agenda_date' => '2026-08-17'],
            ],
        ]);

        $this->assertFalse($v->fails());
    }

    public function test_valid_move_action_passes(): void
    {
        $v = $this->validate([
            'schedule_version' => 0,
            'assignments' => [
                ['customer_id' => 456, 'action' => 'move', 'seller_id' => 3573, 'agenda_date' => '2026-08-18'],
            ],
        ]);

        $this->assertFalse($v->fails());
    }

    public function test_valid_remove_action_with_nulls_passes(): void
    {
        $v = $this->validate([
            'schedule_version' => 10,
            'assignments' => [
                ['customer_id' => 789, 'action' => 'remove', 'seller_id' => null, 'agenda_date' => null],
            ],
        ]);

        $this->assertFalse($v->fails());
    }

    public function test_maximum_batch_size_passes(): void
    {
        $assignments = array_fill(0, 50, ['customer_id' => 1, 'action' => 'assign', 'seller_id' => 3572, 'agenda_date' => '2026-08-17']);

        $v = $this->validate(['schedule_version' => 1, 'assignments' => $assignments]);

        $this->assertFalse($v->fails());
    }

    // -------------------------------------------------------------------------
    // Casos inválidos — schedule_version
    // -------------------------------------------------------------------------

    public function test_missing_schedule_version_fails(): void
    {
        $v = $this->validate([
            'assignments' => [['customer_id' => 1, 'action' => 'assign']],
        ]);

        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('schedule_version', $v->errors()->toArray());
    }

    public function test_negative_schedule_version_fails(): void
    {
        $v = $this->validate([
            'schedule_version' => -1,
            'assignments' => [['customer_id' => 1, 'action' => 'assign']],
        ]);

        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('schedule_version', $v->errors()->toArray());
    }

    public function test_string_schedule_version_fails(): void
    {
        $v = $this->validate([
            'schedule_version' => 'abc',
            'assignments' => [['customer_id' => 1, 'action' => 'assign']],
        ]);

        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('schedule_version', $v->errors()->toArray());
    }

    // -------------------------------------------------------------------------
    // Casos inválidos — assignments
    // -------------------------------------------------------------------------

    public function test_empty_assignments_array_fails(): void
    {
        $v = $this->validate(['schedule_version' => 1, 'assignments' => []]);

        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('assignments', $v->errors()->toArray());
    }

    public function test_exceeding_max_50_assignments_fails(): void
    {
        $assignments = array_fill(0, 51, ['customer_id' => 1, 'action' => 'assign', 'seller_id' => 1, 'agenda_date' => '2026-08-17']);

        $v = $this->validate(['schedule_version' => 1, 'assignments' => $assignments]);

        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('assignments', $v->errors()->toArray());
    }

    public function test_invalid_action_value_fails(): void
    {
        $v = $this->validate([
            'schedule_version' => 1,
            'assignments' => [['customer_id' => 1, 'action' => 'delete']],
        ]);

        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('assignments.0.action', $v->errors()->toArray());
    }

    public function test_zero_customer_id_fails(): void
    {
        $v = $this->validate([
            'schedule_version' => 1,
            'assignments' => [['customer_id' => 0, 'action' => 'assign']],
        ]);

        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('assignments.0.customer_id', $v->errors()->toArray());
    }

    public function test_invalid_date_format_fails(): void
    {
        $v = $this->validate([
            'schedule_version' => 1,
            'assignments' => [['customer_id' => 1, 'action' => 'assign', 'seller_id' => 1, 'agenda_date' => '19/08/2026']],
        ]);

        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('assignments.0.agenda_date', $v->errors()->toArray());
    }

    // -------------------------------------------------------------------------
    // toApiPayload() — construcción desde validated()
    // -------------------------------------------------------------------------

    public function test_to_api_payload_builds_assign_correctly(): void
    {
        $request = BatchAssignmentRequest::create('/', 'POST', [
            'schedule_version' => 42,
            'assignments' => [
                ['customer_id' => 123, 'action' => 'assign', 'seller_id' => 3572, 'agenda_date' => '2026-08-17'],
            ],
        ]);
        $request->setContainer(app())->validateResolved();

        $payload = $request->toApiPayload();

        $this->assertSame(42, $payload['schedule_version']);
        $this->assertCount(1, $payload['assignments']);
        $this->assertSame(123, $payload['assignments'][0]['customer_id']);
        $this->assertSame('assign', $payload['assignments'][0]['action']);
        $this->assertSame(3572, $payload['assignments'][0]['seller_id']);
        $this->assertSame('2026-08-17', $payload['assignments'][0]['agenda_date']);
    }

    public function test_to_api_payload_normalizes_remove_action(): void
    {
        $request = BatchAssignmentRequest::create('/', 'POST', [
            'schedule_version' => 10,
            'assignments' => [
                ['customer_id' => 789, 'action' => 'remove'],
            ],
        ]);
        $request->setContainer(app())->validateResolved();

        $payload = $request->toApiPayload();
        $item = $payload['assignments'][0];

        $this->assertSame('remove', $item['action']);
        $this->assertArrayNotHasKey('seller_id', $item);
        $this->assertArrayNotHasKey('agenda_date', $item);
    }

    public function test_to_api_payload_casts_ids_to_int(): void
    {
        $request = BatchAssignmentRequest::create('/', 'POST', [
            'schedule_version' => '5',
            'assignments' => [
                ['customer_id' => '99', 'action' => 'move', 'seller_id' => '3574', 'agenda_date' => '2026-08-20'],
            ],
        ]);
        $request->setContainer(app())->validateResolved();

        $payload = $request->toApiPayload();

        $this->assertIsInt($payload['schedule_version']);
        $this->assertIsInt($payload['assignments'][0]['customer_id']);
        $this->assertIsInt($payload['assignments'][0]['seller_id']);
    }
}
