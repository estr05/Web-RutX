<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ApiClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

/**
 * ApiClientTest
 *
 * Valida el único punto HTTP de RutX Web (guidelines §1.1 y §1.5):
 * - El token viaja como Authorization: Bearer desde la sesión cifrada.
 * - Accept/Content-Type JSON, timeout y TLS configurados por request.
 * - Envelope de éxito (data, meta, trace_id) y errores funcionales.
 * - trace_id registrado en el canal de log `api_errors`.
 * - Errores remotos nunca exponen payload sensible ni excepción al usuario; `errors` se conserva dentro del envelope interno conforme al contrato v2.
 */
class ApiClientTest extends TestCase
{
    private const BASE_URL = 'https://sincronizador.example.test/api/v2/web';

    protected function setUp(): void
    {
        parent::setUp();

        // Configuración determinista independiente del .env local.
        config()->set('services.api_web', [
            'base_url' => self::BASE_URL,
            'auth_url' => self::BASE_URL.'/auth',
            'timeout' => 10,
            'connect_timeout' => 5,
            'verify_tls' => true,
            'client_id' => 'rutx-web-client',
            'client_secret' => null,
        ]);

        $this->session(['api_token' => 'web-token-test']);
    }

    // -------------------------------------------------------------------------
    // Envelope de éxito
    // -------------------------------------------------------------------------

    public function test_get_maps_successful_envelope_with_trace_id(): void
    {
        Http::fake([
            '*/api/v2/web/dashboard*' => Http::response([
                'data' => ['total_sales' => 15420.5, 'currency' => 'MXN'],
                'meta' => ['generated_at' => '2026-08-14T10:00:00-06:00'],
                'trace_id' => '01J-test-success',
            ]),
        ]);

        $result = app(ApiClient::class)->get('/dashboard', ['date_from' => '2026-08-14']);

        $this->assertTrue($result['success']);
        $this->assertSame('01J-test-success', $result['trace_id']);
        $this->assertSame(['total_sales' => 15420.5, 'currency' => 'MXN'], $result['data']);

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'GET'
                && $request->hasHeader('Authorization', 'Bearer web-token-test')
                && $request->hasHeader('Accept', 'application/json')
                && $request->hasHeader('Content-Type', 'application/json')
                && str_starts_with($request->url(), self::BASE_URL.'/dashboard')
                && str_contains($request->url(), 'date_from=2026-08-14');
        });
    }

    public function test_post_sends_json_payload_with_custom_headers(): void
    {
        Http::fake([
            '*/api/v2/web/sales/25/cancellation-requests' => Http::response([
                'data' => ['id' => 1001, 'status' => 'pending'],
                'trace_id' => '01J-test-post',
            ], 201),
        ]);

        $result = app(ApiClient::class)->post(
            '/sales/25/cancellation-requests',
            ['reason' => 'Devolución'],
            ['Idempotency-Key' => 'idem-123'],
        );

        $this->assertTrue($result['success']);
        $this->assertSame(['id' => 1001, 'status' => 'pending'], $result['data']);

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && $request->hasHeader('Idempotency-Key', 'idem-123')
                && $request->hasHeader('Content-Type', 'application/json')
                && $request->data() === ['reason' => 'Devolución'];
        });
    }

    // -------------------------------------------------------------------------
    // Envelope de error y respuestas inesperadas
    // -------------------------------------------------------------------------

    public function test_get_reports_invalid_envelope_when_data_is_missing(): void
    {
        Http::fake([
            '*/api/v2/web/dashboard*' => Http::response(['trace_id' => '01J-no-data'], 200),
        ]);

        $result = app(ApiClient::class)->get('/dashboard');

        $this->assertFalse($result['success']);
        $this->assertSame('INVALID_ENVELOPE', $result['code']);
        $this->assertSame('Respuesta inesperada del servicio.', $result['message']);
        $this->assertSame('01J-no-data', $result['trace_id']);
    }

    public function test_get_maps_401_to_session_invalidation(): void
    {
        Http::fake([
            '*/api/v2/web/dashboard*' => Http::response([
                'code' => 'UNAUTHENTICATED',
                'message' => 'Token inválido.',
                'trace_id' => '01J-401',
            ], 401),
        ]);

        $result = app(ApiClient::class)->get('/dashboard');

        $this->assertFalse($result['success']);
        $this->assertSame('UNAUTHORIZED', $result['code']);
        $this->assertSame('Debes iniciar sesión nuevamente.', $result['message']);
        $this->assertSame('01J-401', $result['trace_id']);
        $this->assertNull(
            session('api_token'),
            'Un 401 debe invalidar la sesión cifrada de inmediato.',
        );
    }

    public function test_post_maps_422_validation_error_with_errors_envelope(): void
    {
        Http::fake([
            '*/api/v2/web/cancellation-requests*' => Http::response([
                'code' => 'VALIDATION_ERROR',
                'message' => 'El motivo es obligatorio.',
                'errors' => ['reason' => ['El motivo es obligatorio.']],
                'trace_id' => '01J-422',
            ], 422),
        ]);

        $result = app(ApiClient::class)->post('/cancellation-requests', ['sale_id' => 25]);

        $this->assertFalse($result['success']);
        $this->assertSame('VALIDATION_ERROR', $result['code']);
        $this->assertSame(['reason' => ['El motivo es obligatorio.']], $result['errors']);
        $this->assertSame('El motivo es obligatorio.', $result['message']);
        $this->assertSame('01J-422', $result['trace_id']);
    }

    public function test_get_maps_500_to_functional_error(): void
    {
        Http::fake([
            '*/api/v2/web/dashboard*' => Http::response([
                'code' => 'SERVER_ERROR',
                'message' => 'Error interno.',
                'trace_id' => '01J-500',
            ], 500),
        ]);

        $result = app(ApiClient::class)->get('/dashboard');

        $this->assertFalse($result['success']);
        $this->assertSame('API_UNAVAILABLE', $result['code']);
        $this->assertSame('No se pudo conectar con el servicio.', $result['message']);
        $this->assertSame('01J-500', $result['trace_id']);
    }

    public function test_connection_failure_returns_functional_message(): void
    {
        Http::fake(fn (Request $request) => throw new ConnectionException('Connection refused'));

        $result = app(ApiClient::class)->get('/dashboard');

        $this->assertFalse($result['success']);
        $this->assertSame('API_UNAVAILABLE', $result['code']);
        $this->assertSame('No se pudo conectar con el servicio.', $result['message']);
    }

    // -------------------------------------------------------------------------
    // Trazabilidad: trace_id en el canal api_errors
    // -------------------------------------------------------------------------

    public function test_error_response_logs_trace_id_in_api_errors_channel(): void
    {
        $logger = Mockery::mock();
        $logger->shouldReceive('error')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'ApiClient error'
                    && $context['code'] === 'API_UNAVAILABLE'
                    && $context['trace_id'] === '01J-500';
            });

        Log::shouldReceive('channel')->with('api_errors')->once()->andReturn($logger);

        Http::fake([
            '*/api/v2/web/dashboard*' => Http::response([
                'code' => 'SERVER_ERROR',
                'message' => 'Error interno.',
                'trace_id' => '01J-500',
            ], 500),
        ]);

        app(ApiClient::class)->get('/dashboard');
    }

    // -------------------------------------------------------------------------
    // Token de sesión y configuración
    // -------------------------------------------------------------------------

    public function test_requires_web_token_in_encrypted_session(): void
    {
        session()->forget('api_token');

        $result = app(ApiClient::class)->get('/dashboard');

        $this->assertFalse($result['success']);
        $this->assertSame('UNAUTHORIZED', $result['code']);
        $this->assertSame('Debes iniciar sesión nuevamente.', $result['message']);
        Http::assertNothingSent();
    }

    public function test_config_wires_api_web_section_and_api_errors_channel(): void
    {
        // Se lee el archivo de configuración directamente (sin el override del setUp)
        // para validar el cableado real de API_WEB_* hacia la sección api_web.
        $services = require config_path('services.php');

        $this->assertSame(env('API_WEB_BASE_URL'), $services['api_web']['base_url'] ?? null);
        $this->assertSame((int) env('API_WEB_TIMEOUT', 10), $services['api_web']['timeout']);
        $this->assertSame((int) env('API_WEB_CONNECT_TIMEOUT', 5), $services['api_web']['connect_timeout']);
        $this->assertSame((bool) env('API_WEB_VERIFY_TLS', true), $services['api_web']['verify_tls']);

        $this->assertSame(
            storage_path('logs/api_errors.log'),
            config('logging.channels.api_errors.path'),
            'Debe existir el canal de log api_errors para trazabilidad de errores.',
        );
    }
}
