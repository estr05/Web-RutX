<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Routes;

use App\Livewire\Routes\Agenda;
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

    public function test_renders_successfully()
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

    public function test_displays_error_on_api_failure()
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
}
