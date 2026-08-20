<?php

declare(strict_types=1);

namespace Tests\Feature\Requests;

use App\Http\Requests\BatchAssignmentRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class BatchAssignmentRequestTest extends TestCase
{
    public function test_assign_requires_seller_id_and_agenda_date(): void
    {
        $payload = [
            'schedule_version' => 1,
            'assignments' => [
                ['customer_id' => 123, 'action' => 'assign', 'seller_id' => null, 'agenda_date' => null],
            ],
        ];

        $validator = Validator::make($payload, (new BatchAssignmentRequest)->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('assignments.0.seller_id', $validator->errors()->toArray());
        $this->assertArrayHasKey('assignments.0.agenda_date', $validator->errors()->toArray());
    }

    public function test_remove_does_not_require_seller_id_or_agenda_date(): void
    {
        $payload = [
            'schedule_version' => 1,
            'assignments' => [
                ['customer_id' => 789, 'action' => 'remove', 'seller_id' => null, 'agenda_date' => null],
            ],
        ];

        $validator = Validator::make($payload, (new BatchAssignmentRequest)->rules());
        $this->assertTrue($validator->passes());
    }

    public function test_format_api_payload_strips_null_fields_for_remove(): void
    {
        $validated = [
            'schedule_version' => 5,
            'assignments' => [
                ['customer_id' => 789, 'action' => 'remove', 'seller_id' => null, 'agenda_date' => null],
            ],
        ];

        $result = BatchAssignmentRequest::formatApiPayload($validated);

        $this->assertEquals(5, $result['schedule_version']);
        $this->assertCount(1, $result['assignments']);
        $this->assertArrayNotHasKey('seller_id', $result['assignments'][0]);
        $this->assertArrayNotHasKey('agenda_date', $result['assignments'][0]);
        $this->assertEquals('remove', $result['assignments'][0]['action']);
    }
}
