<?php

declare(strict_types=1);

namespace Tests\Feature\Middleware;

use App\Http\Middleware\AuthSession;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * AuthSessionMiddlewareTest
 *
 * Verifica el middleware `auth.session` (primera barrera de autenticación):
 * - Sin JWT web en sesión cifrada → redirección a login.
 * - Con JWT web en sesión → la ruta protegida responde normalmente.
 * - El alias queda registrado para las rutas web (guidelines §1.2).
 */
class AuthSessionMiddlewareTest extends TestCase
{
    private const PROTECTED_URI = '/_test/auth-session-protected';

    protected function setUp(): void
    {
        parent::setUp();

        // Ruta de login temporal: el módulo de autenticación llega en una etapa posterior.
        Route::get('/login', fn () => 'login page')->name('login');

        // En pruebas, el nombre se fija después de añadir la ruta a la colección
        // y no actualiza los name lookups; se refrescan para que route('login')
        // resuelva dentro del middleware.
        app('router')->getRoutes()->refreshNameLookups();
    }

    public function test_guest_without_api_token_is_redirected_to_login(): void
    {
        Route::get(self::PROTECTED_URI, fn () => 'protected')->middleware('auth.session');

        $this->get(self::PROTECTED_URI)
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_with_api_token_passes_through(): void
    {
        $this->session(['api_token' => 'web-token-test']);

        Route::get(self::PROTECTED_URI, fn () => 'protected')->middleware('auth.session');

        $this->get(self::PROTECTED_URI)
            ->assertOk()
            ->assertSee('protected');
    }

    public function test_auth_session_alias_is_registered(): void
    {
        $this->assertSame(
            AuthSession::class,
            app('router')->getMiddleware()['auth.session'] ?? null,
        );
    }
}
