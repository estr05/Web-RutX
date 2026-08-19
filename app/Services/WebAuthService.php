<?php

declare(strict_types=1);

namespace App\Services;

/**
 * WebAuthService — vertical de autenticación de oficina (contrato v2 §5).
 *
 * Flujo obligatorio: POST /api/v2/web/auth/login → JWT web con scope=web;
 * el token y los claims (user, roles, permissions, zone_ids) se conservan
 * EXCLUSIVAMENTE en la sesión cifrada de Laravel (nunca en localStorage ni
 * en cookies legibles). GET /api/v2/web/auth/me refresca la identidad y los
 * permisos efectivos sin re-autenticar.
 *
 * Regla de stubs: la autenticación NUNCA usa stubs. Un stub de login
 * fabricaría credenciales y permisos, lo que viola la regla del sprint
 * "no hay datos ficticios presentados como datos reales". Sin Sincronizador
 * accesible, el login responde error funcional y no existe sesión.
 */
class WebAuthService
{
    public function __construct(private readonly ApiClient $api) {}

    /**
     * Inicia sesión contra el Sincronizador y materializa la sesión cifrada.
     *
     * @return array{success: bool, code?: string, message?: string}
     */
    public function login(string $username, string $password): array
    {
        $result = $this->api->postPublic('/auth/login', [
            'username' => $username,
            'password' => $password,
        ]);

        if (! $result['success']) {
            return $result;
        }

        $data = $result['data'] ?? [];
        $accessToken = (string) ($data['access_token'] ?? '');

        if ($accessToken === '') {
            return ['success' => false, 'code' => 'INVALID_RESPONSE', 'message' => 'El servidor no devolvió un token de acceso válido.'];
        }

        $scopes = (array) ($data['scopes'] ?? $data['scope'] ?? []);
        if (! in_array('web', $scopes, true)) {
            return ['success' => false, 'code' => 'SCOPE_REQUIRED', 'message' => 'El token no tiene el alcance web requerido.'];
        }

        session()->put('api_token', $accessToken);
        session()->put('user', [
            'username' => (string) ($data['user']['username'] ?? $username),
            'display_name' => (string) ($data['user']['display_name'] ?? $username),
        ]);
        session()->put('permissions', (array) ($data['permissions'] ?? []));
        session()->put('roles', (array) ($data['roles'] ?? []));
        session()->put('zone_ids', array_map('intval', (array) ($data['zone_ids'] ?? [])));
        session()->put('auth_expires_at', $data['expires_at'] ?? null);

        return ['success' => true];
    }

    /**
     * Refresca identidad y permisos con GET /api/v2/web/auth/me.
     * Si la API responde 401, ApiClient ya invalidó la sesión.
     */
    public function refreshIdentity(): array
    {
        $result = $this->api->get('/auth/me');

        if (! $result['success']) {
            return $result;
        }

        $data = $result['data'] ?? [];

        if (isset($data['user'])) {
            session()->put('user', [
                'username' => (string) ($data['user']['username'] ?? session('user.username', '')),
                'display_name' => (string) ($data['user']['display_name'] ?? session('user.display_name', '')),
            ]);
        }
        session()->put('permissions', (array) ($data['permissions'] ?? []));
        session()->put('roles', (array) ($data['roles'] ?? []));
        session()->put('zone_ids', array_map('intval', (array) ($data['zone_ids'] ?? [])));

        return ['success' => true];
    }

    /**
     * Cierra la sesión local. La revocación del token en el Sincronizador
     * es posterior en el contrato (POST /auth/logout); aquí se invalidan
     * todas las claves de sesión cifrada de inmediato.
     */
    public function logout(): void
    {
        session()->forget(['api_token', 'user', 'permissions', 'roles', 'zone_ids', 'auth_expires_at']);
    }

    public function isAuthenticated(): bool
    {
        return session()->has('api_token');
    }
}
