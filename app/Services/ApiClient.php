<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ApiClient — único punto de salida HTTP de RutX Web.
 *
 * Reglas de arquitectura (Docs/guidelines.md §1.1 y §1.5):
 * - Consume EXCLUSIVAMENTE el contrato /api/v2/web/* del Sincronizador.
 * - El JWT web se lee de la sesión cifrada de Laravel (session('api_token')),
 *   jamás se expone al navegador, a localStorage ni a atributos data-.
 * - Toda petición usa Accept/Content-Type JSON, timeout explícito y TLS verificado.
 * - El navegador nunca llama al Sincronizador: no existe fetch/Axios directo.
 *
 * Envelope de éxito del contrato: data, [meta], [filters], trace_id.
 * Envelope de error del contrato: code, message, [errors], trace_id.
 * Los errores se traducen a mensajes funcionales; trace_id se conserva en el
 * canal de log `api_errors` y nunca llega al usuario.
 */
class ApiClient
{
    /**
     * Construye la petición base hacia el Sincronizador (solo /api/v2/web/*).
     *
     * @throws ConnectionException
     */
    public function request(): PendingRequest
    {
        $baseUrl = config('services.api_web.base_url');

        if (! is_string($baseUrl) || $baseUrl === '') {
            throw new ConnectionException('API_WEB_BASE_URL no está configurada.');
        }

        return Http::baseUrl($baseUrl)
            ->acceptJson()
            ->asJson()
            ->timeout(config('services.api_web.timeout'))
            ->connectTimeout(config('services.api_web.connect_timeout'))
            ->withOptions(['verify' => (bool) config('services.api_web.verify_tls', true)]);
    }

    /**
     * GET autenticado. Mapea el envelope del contrato v2.
     */
    public function get(string $endpoint, array $query = []): array
    {
        $token = $this->tokenOrUnauthorized();

        if ($token === null) {
            return $this->error('UNAUTHORIZED', __('Debes iniciar sesión nuevamente.'), null);
        }

        try {
            $response = $this->request()
                ->withToken($token)
                ->get($endpoint, $query);
        } catch (ConnectionException $e) {
            return $this->error('API_UNAVAILABLE', __('No se pudo conectar con el servicio.'), null);
        }

        return $this->resolve($response);
    }

    /**
     * POST autenticado para comandos. Solo reintentable con Idempotency-Key.
     */
    public function post(string $endpoint, array $payload, array $headers = []): array
    {
        $token = $this->tokenOrUnauthorized();

        if ($token === null) {
            return $this->error('UNAUTHORIZED', __('Debes iniciar sesión nuevamente.'), null);
        }

        try {
            $response = $this->request()
                ->withToken($token)
                ->withHeaders($headers)
                ->post($endpoint, $payload);
        } catch (ConnectionException $e) {
            return $this->error('API_UNAVAILABLE', __('No se pudo conectar con el servicio.'), null);
        }

        return $this->resolve($response);
    }

    /**
     * POST público SIN token — EXCLUSIVO para POST /api/v2/web/auth/login,
     * único endpoint público del contrato v2. Mantiene el único punto de
     * salida HTTP de la aplicación; cualquier otro uso es error de diseño.
     */
    public function postPublic(string $endpoint, array $payload, array $headers = []): array
    {
        try {
            $response = $this->request()
                ->withHeaders($headers)
                ->post($endpoint, $payload);
        } catch (ConnectionException $e) {
            return $this->error('API_UNAVAILABLE', __('No se pudo conectar con el servicio.'), null);
        }

        return $this->resolve($response);
    }

    /**
     * Token web desde la sesión cifrada del servidor.
     *
     * Sin token devuelve null y limpia las claves residuales de sesión: NO
     * lanza excepción, porque la primera llamada de un render puede detectar
     * un 401 (que ya invalidó la sesión) y las siguientes no deben abortar
     * el request con un 401 HTTP — el redirect a login lo hace auth.session
     * en el siguiente request.
     */
    private function tokenOrUnauthorized(): ?string
    {
        $token = session('api_token');

        if (! $token) {
            session()->forget(['api_token', 'user', 'permissions', 'roles', 'zone_ids', 'auth_expires_at']);

            return null;
        }

        return (string) $token;
    }

    /**
     * Valida el envelope del contrato v2 antes de devolver datos.
     */
    private function resolve(Response $response): array
    {
        $json = $response->json();

        // 401 = token vencido/revocado: se invalida la sesión cifrada de
        // inmediato (auth.session redirige a login en el siguiente request).
        if ($response->status() === 401) {
            session()->forget(['api_token', 'user', 'permissions', 'roles', 'zone_ids', 'auth_expires_at']);

            return $this->error('UNAUTHORIZED', __('Debes iniciar sesión nuevamente.'), $json);
        }

        if (! $response->successful() || ! is_array($json)) {
            if ($response->status() === 501 && isset($json['code'])) {
                return $this->error($json['code'], $json['message'] ?? __('Funcionalidad no disponible.'), $json);
            }

            if ($response->status() === 503 && isset($json['code'])) {
                return $this->error($json['code'], $json['message'] ?? __('Servicio en mantenimiento.'), $json);
            }

            if ($response->status() === 422 && isset($json['code'])) {
                return $this->error($json['code'], $json['message'] ?? __('Errores de validación.'), $json);
            }

            if ($response->status() >= 400 && $response->status() < 500
                && isset($json['code'], $json['message'])) {
                return $this->error($json['code'], __('Ocurrió un error en el servicio.'), $json);
            }

            return $this->error('API_UNAVAILABLE', __('No se pudo conectar con el servicio.'), $json);
        }

        if (isset($json['code'], $json['message'])) {
            return $this->error($json['code'], __('Ocurrió un error en el servicio.'), $json);
        }

        if (! isset($json['data'])) {
            return $this->error('INVALID_ENVELOPE', __('Respuesta inesperada del servicio.'), $json);
        }

        return ['success' => true] + $json;
    }

    /**
     * Envelope de error funcional: conserva `errors` estructurados del contrato,
     * pero nunca expone tokens, payload sensible ni excepciones.
     * El trace_id se registra en el canal `api_errors` para trazabilidad.
     */
    private function error(string $code, string $userMessage, mixed $raw): array
    {
        // Una API intermediaria puede devolver HTML, texto plano o un body vacío.
        // Normalizar aquí evita que el manejo del error produzca un segundo error.
        $rawEnvelope = is_array($raw) ? $raw : [];

        Log::channel('api_errors')->error('ApiClient error', [
            'code' => $code,
            'trace_id' => $rawEnvelope['trace_id'] ?? null,
            'status' => $rawEnvelope['status'] ?? null,
        ]);

        return [
            'success' => false,
            'code' => $code,
            'message' => $userMessage,
            'errors' => $rawEnvelope['errors'] ?? null,
            'trace_id' => $rawEnvelope['trace_id'] ?? null,
        ];
    }
}
