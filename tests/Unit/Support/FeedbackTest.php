<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Feedback;
use InvalidArgumentException;
use Tests\TestCase;

class FeedbackTest extends TestCase
{
    public function test_success_matches_the_three_second_flutter_default(): void
    {
        $feedback = Feedback::success('Cambios guardados correctamente.');

        $this->assertSame('success', $feedback['type']);
        $this->assertSame('Cambios guardados correctamente.', $feedback['message']);
        $this->assertSame(3_000, $feedback['duration']);
        $this->assertNull($feedback['title']);
        $this->assertNull($feedback['action']);
    }

    public function test_recoverable_error_exposes_only_an_event_based_retry_action(): void
    {
        $feedback = Feedback::error(
            message: 'No fue posible consultar la API. Intenta nuevamente.',
            isRecoverable: true,
            retryEvent: 'sales.retry-load',
        );

        $this->assertSame('error', $feedback['type']);
        $this->assertSame('Error', $feedback['title']);
        $this->assertSame(5_000, $feedback['duration']);
        $this->assertSame([
            'label' => 'REINTENTAR',
            'event' => 'sales.retry-load',
        ], $feedback['action']);
    }

    public function test_non_recoverable_error_hides_the_retry_action(): void
    {
        $feedback = Feedback::error(
            message: 'No tienes permiso para ejecutar esta acción.',
            isRecoverable: false,
            retryEvent: 'sales.retry-load',
        );

        $this->assertNull($feedback['action']);
    }

    public function test_flash_replaces_the_previous_pending_notification(): void
    {
        Feedback::flash(Feedback::info('Primera notificación.'));
        Feedback::flash(Feedback::warning('Segunda notificación.'));

        $this->assertSame(
            Feedback::warning('Segunda notificación.'),
            session()->get('rutx_feedback'),
        );
    }

    public function test_feedback_rejects_an_untrusted_action_event(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Feedback::info(
            message: 'Prueba.',
            actionLabel: 'Reintentar',
            actionEvent: 'sales retry',
        );
    }
}
