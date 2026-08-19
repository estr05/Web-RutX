<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Services\WebAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * LoginController — flujo de inicio/cierre de sesión de oficina.
 *
 * - GET  /login  → formulario (destino de auth.session).
 * - POST /login  → POST /api/v2/web/auth/login con throttle local 5/minuto.
 * - POST /logout → invalida la sesión cifrada (revocación remota: posterior).
 *
 * El token nunca llega a la vista; los errores de API se traducen a
 * mensajes funcionales sin exponer payload, stack ni credenciales.
 */
class LoginController extends Controller
{
    public function show(): View
    {
        return view('modules.auth.login');
    }

    public function store(LoginRequest $request, WebAuthService $auth): RedirectResponse
    {
        $validated = $request->validated();

        $result = $auth->login($validated['username'], $validated['password']);

        if (! $result['success']) {
            return back()
                ->withInput($request->only('username'))
                ->withErrors(['credentials' => $this->mensajeDeError($result)]);
        }

        // Regenerar ID de sesión tras autenticación exitosa (session fixation).
        $request->session()->regenerate();

        return redirect()->intended(route('venta.reportes'));
    }

    public function destroy(Request $request, WebAuthService $auth): RedirectResponse
    {
        $auth->logout();

        // Invalidar sesión y regenerar token CSRF (logout limpio).
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function mensajeDeError(array $result): string
    {
        if (($result['code'] ?? null) === 'UNAUTHORIZED') {
            return __('Usuario o contraseña incorrectos.');
        }

        if (($result['code'] ?? null) === 'WEB_SCOPE_REQUIRED' || ($result['code'] ?? null) === 'FORBIDDEN') {
            return __('Esta cuenta no está habilitada para la plataforma de oficina.');
        }

        return __('No fue posible iniciar sesión. Revisa el estado del servicio.');
    }
}
