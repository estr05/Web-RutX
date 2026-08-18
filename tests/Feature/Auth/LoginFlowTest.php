<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * LoginFlowTest
 *
 * Flujo de inicio de sesión de oficina (Sprint 4 · Bloque 0):
 * - GET /login renderiza el formulario con CSRF.
 * - POST /login con credenciales válidas inicia sesión y redirige al portal.
 * - Credenciales inválidas regresan error funcional sin exponer payload.
 * - El throttle local (5 intentos/minuto) bloquea ráfagas de intentos.
 * - POST /logout cierra la sesión y redirige al formulario.
 * - Un 401 de la API invalida la sesión en cualquier punto del flujo.
 */
class LoginFlowTest extends TestCase
{
    private const BASE_URL = 'https://sincronizador.example.test/api/v2/web';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.api_web', [
            'base_url' => self::BASE_URL,
            'auth_url' => self::BASE_URL.'/auth',
            'timeout' => 10,
            'connect_timeout' => 5,
            'verify_tls' => true,
            'client_id' => 'rutx-web-client',
            'client_secret' => null,
        ]);
    }

    public function test_guest_see_login_form_with_csrf(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('Iniciar sesión');
        $response->assertSee('csrf-token', false);
        $response->assertSee(route('login.store'), false);
    }

    public function test_valid_credentials_start_session_and_redirect_to_portal(): void
    {
        Http::fake([
            '*/api/v2/web/auth/login' => Http::response([
                'data' => [
                    'access_token' => 'jwt-web-scope',
                    'expires_at' => '2026-08-16T12:00:00-06:00',
                    'user' => ['username' => 'admin.rutx', 'display_name' => 'Admin RutX'],
                    'roles' => ['administrador'],
                    'permissions' => ['reports.read'],
                    'zone_ids' => [1],
                ],
                'trace_id' => '01J-feature-login-ok',
            ], 200),
        ]);

        $response = $this->post(route('login.store'), [
            'username' => 'admin.rutx',
            'password' => 's3cret',
        ]);

        $response->assertRedirect(route('venta.reportes'));
        $this->assertSame('jwt-web-scope', session('api_token'));
        $this->assertSame('admin.rutx', session('user.username'));
        $this->assertSame(['administrador'], session('roles'));

        // El portal ya está accesible con la sesión materializada.
        $this->get(route('venta.reportes'))->assertOk();
    }

    public function test_invalid_credentials_show_functional_error_and_no_session(): void
    {
        Http::fake([
            '*/api/v2/web/auth/login' => Http::response([
                'code' => 'UNAUTHENTICATED',
                'message' => 'Credenciales inválidas.',
                'trace_id' => '01J-feature-login-401',
            ], 401),
        ]);

        $response = $this->post(route('login.store'), [
            'username' => 'admin.rutx',
            'password' => 'mala-clave',
        ]);

        $response->assertSessionHasErrors('credentials');
        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors(['username', 'password']);
        $this->assertNull(session('api_token'));
    }

    public function test_rapid_attempts_are_throttled_locally(): void
    {
        Http::fake([
            '*/api/v2/web/auth/login' => Http::response([
                'code' => 'UNAUTHENTICATED',
                'message' => 'Credenciales inválidas.',
                'trace_id' => '01J-feature-throttle',
            ], 401),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.store'), [
                'username' => 'admin.rutx',
                'password' => 'mala-clave',
            ])->assertSessionHasErrors('credentials');
        }

        $this->post(route('login.store'), [
            'username' => 'admin.rutx',
            'password' => 'mala-clave',
        ])->assertStatus(429);
    }

    public function test_logout_clears_session_and_redirects_to_login(): void
    {
        $this->session([
            'api_token' => 'jwt-web-scope',
            'user' => ['username' => 'admin.rutx', 'display_name' => 'Admin RutX'],
            'permissions' => ['reports.read'],
            'roles' => ['administrador'],
            'zone_ids' => [1],
        ]);

        $response = $this->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertNull(session('api_token'));
        $this->assertNull(session('permissions'));
    }

    public function test_api_401_anywhere_invalidates_session_and_sends_back_to_login(): void
    {
        $this->session([
            'api_token' => 'jwt-expirado',
            'user' => ['username' => 'admin.rutx', 'display_name' => 'Admin RutX'],
            'permissions' => ['reports.read'],
            'roles' => ['administrador'],
            'zone_ids' => [1],
        ]);

        Http::fake([
            '*/api/v2/web/dashboard*' => Http::response([
                'code' => 'UNAUTHENTICATED',
                'message' => 'Token expirado.',
                'trace_id' => '01J-feature-401-anywhere',
            ], 401),
        ]);

        // El render detecta el 401: la sesión se invalida en el acto.
        $this->get(route('venta.reportes'))->assertOk();
        $this->assertNull(session('api_token'));
        $this->assertNull(session('permissions'));

        // Sin token en sesión, el siguiente request del portal redirige a login.
        $this->get(route('venta.reportes'))
            ->assertRedirect(route('login'));

        $this->assertNull(session('api_token'));
    }
}
