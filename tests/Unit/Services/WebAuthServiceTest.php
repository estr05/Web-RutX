<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\WebAuthService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * WebAuthServiceTest
 *
 * Valida la vertical de autenticación de oficina (contrato v2 §5):
 * - POST /auth/login materializa la sesión cifrada con token + claims.
 * - Los errores de API se propagan como resultado funcional, sin lanzar.
 * - GET /auth/me refresca identidad y permisos efectivos.
 * - Un 401 remoto invalida TODAS las claves de sesión de inmediato.
 * - logout limpia la sesión por completo.
 */
class WebAuthServiceTest extends TestCase
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

    public function test_login_success_materializes_encrypted_session(): void
    {
        Http::fake([
            '*/api/v2/web/auth/login' => Http::response([
                'data' => [
                    'access_token' => 'jwt-web-scope',
                    'expires_at' => '2026-08-16T12:00:00-06:00',
                    'user' => [
                        'username' => 'admin.rutx',
                        'display_name' => 'Admin RutX',
                    ],
                    'roles' => ['administrador'],
                    'permissions' => ['venta.ver', 'inventario.ver'],
                    'zone_ids' => [1, 3],
                ],
                'trace_id' => '01J-login-ok',
            ], 200),
        ]);

        $result = app(WebAuthService::class)->login('admin.rutx', 's3cret');

        $this->assertTrue($result['success']);
        $this->assertSame('jwt-web-scope', session('api_token'));
        $this->assertSame('admin.rutx', session('user.username'));
        $this->assertSame(['administrador'], session('roles'));
        $this->assertSame(['venta.ver', 'inventario.ver'], session('permissions'));
        $this->assertSame([1, 3], session('zone_ids'));

        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/auth/login')
            && $request->data() === ['username' => 'admin.rutx', 'password' => 's3cret']);
    }

    public function test_login_401_returns_functional_error_without_session(): void
    {
        Http::fake([
            '*/api/v2/web/auth/login' => Http::response([
                'code' => 'UNAUTHENTICATED',
                'message' => 'Credenciales inválidas.',
                'trace_id' => '01J-login-401',
            ], 401),
        ]);

        $result = app(WebAuthService::class)->login('admin.rutx', 'mala-clave');

        $this->assertFalse($result['success']);
        $this->assertSame('UNAUTHORIZED', $result['code']);
        $this->assertNull(session('api_token'));
    }

    public function test_refresh_identity_updates_permissions_and_roles(): void
    {
        $this->session([
            'api_token' => 'jwt-web-scope',
            'user' => ['username' => 'admin.rutx', 'display_name' => 'Admin RutX'],
            'permissions' => ['venta.ver'],
            'roles' => ['administrador'],
            'zone_ids' => [1],
        ]);

        Http::fake([
            '*/api/v2/web/auth/me' => Http::response([
                'data' => [
                    'user' => ['username' => 'admin.rutx', 'display_name' => 'Admin RutX'],
                    'roles' => ['administrador'],
                    'permissions' => ['venta.ver', 'inventario.ver', 'notificaciones.ver'],
                    'zone_ids' => [1, 3],
                ],
                'trace_id' => '01J-me-ok',
            ], 200),
        ]);

        $result = app(WebAuthService::class)->refreshIdentity();

        $this->assertTrue($result['success']);
        $this->assertSame(['venta.ver', 'inventario.ver', 'notificaciones.ver'], session('permissions'));
        $this->assertSame([1, 3], session('zone_ids'));
        $this->assertSame('jwt-web-scope', session('api_token'));
    }

    public function test_refresh_identity_401_invalidates_entire_session(): void
    {
        $this->session([
            'api_token' => 'jwt-expirado',
            'user' => ['username' => 'admin.rutx', 'display_name' => 'Admin RutX'],
            'permissions' => ['venta.ver'],
            'roles' => ['administrador'],
            'zone_ids' => [1],
        ]);

        Http::fake([
            '*/api/v2/web/auth/me' => Http::response([
                'code' => 'UNAUTHENTICATED',
                'message' => 'Token expirado.',
                'trace_id' => '01J-me-401',
            ], 401),
        ]);

        $result = app(WebAuthService::class)->refreshIdentity();

        $this->assertFalse($result['success']);
        $this->assertSame('UNAUTHORIZED', $result['code']);
        $this->assertNull(session('api_token'));
        $this->assertNull(session('user'));
        $this->assertNull(session('permissions'));
        $this->assertNull(session('roles'));
        $this->assertNull(session('zone_ids'));
    }

    public function test_logout_clears_all_authentication_keys(): void
    {
        $this->session([
            'api_token' => 'jwt-web-scope',
            'user' => ['username' => 'admin.rutx', 'display_name' => 'Admin RutX'],
            'permissions' => ['venta.ver'],
            'roles' => ['administrador'],
            'zone_ids' => [1],
            'auth_expires_at' => '2026-08-16T12:00:00-06:00',
        ]);

        $service = app(WebAuthService::class);

        $this->assertTrue($service->isAuthenticated());

        $service->logout();

        $this->assertFalse($service->isAuthenticated());
        $this->assertNull(session('api_token'));
        $this->assertNull(session('user'));
        $this->assertNull(session('permissions'));
        $this->assertNull(session('roles'));
        $this->assertNull(session('zone_ids'));
        $this->assertNull(session('auth_expires_at'));
    }
}
