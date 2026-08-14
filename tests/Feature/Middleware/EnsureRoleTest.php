<?php

declare(strict_types=1);

namespace Tests\Feature\Middleware;

use App\Http\Middleware\EnsureRole;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * EnsureRoleTest
 *
 * Verifica el middleware `role:<rol[,rol...]>` — primera mitad de la
 * autorización doble (guidelines §1.3). Los roles se leen de la sesión
 * cifrada (session('user.roles')), poblada con los claims del JWT web.
 */
class EnsureRoleTest extends TestCase
{
    private const ROLE_URI = '/_test/ensure-role-protected';

    public function test_route_without_roles_in_session_is_forbidden(): void
    {
        Route::get(self::ROLE_URI, fn () => 'ok')->middleware('role:supervisor');

        $this->get(self::ROLE_URI)->assertForbidden();
    }

    public function test_route_with_non_matching_role_is_forbidden(): void
    {
        $this->session(['user' => ['roles' => ['lector']]]);

        Route::get(self::ROLE_URI, fn () => 'ok')->middleware('role:supervisor');

        $this->get(self::ROLE_URI)->assertForbidden();
    }

    public function test_route_with_matching_role_passes_through(): void
    {
        $this->session(['user' => ['roles' => ['supervisor']]]);

        Route::get(self::ROLE_URI, fn () => 'ok')->middleware('role:supervisor');

        $this->get(self::ROLE_URI)
            ->assertOk()
            ->assertSee('ok');
    }

    public function test_route_accepts_any_of_several_allowed_roles(): void
    {
        $this->session(['user' => ['roles' => ['administrador']]]);

        Route::get(self::ROLE_URI, fn () => 'ok')->middleware('role:supervisor,administrador');

        $this->get(self::ROLE_URI)->assertOk();
    }

    public function test_role_alias_is_registered(): void
    {
        $this->assertSame(
            EnsureRole::class,
            app('router')->getMiddleware()['role'] ?? null,
        );
    }
}
