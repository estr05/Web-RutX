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
     */
    public function request(): PendingRequest
    {
        return Http::baseUrl(config('services.api_web.base_url'))
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
        try {
            $response = $this->request()
                ->withToken($this->resolveToken())
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
        try {
            $response = $this->request()
                ->withToken($this->resolveToken())
                ->withHeaders($headers)
                ->post($endpoint, $payload);
        } catch (ConnectionException $e) {
            return $this->error('API_UNAVAILABLE', __('No se pudo conectar con el servicio.'), null);
        }

        return $this->resolve($response);
    }

    /**
     * Token web desde la sesión cifrada del servidor.
     */
    private function resolveToken(): string
    {
        $token = session('api_token');

        abort_unless($token, 401, __('Debes iniciar sesión nuevamente.'));

        return (string) $token;
    }

    /**
     * Valida el envelope del contrato v2 antes de devolver datos.
     */
    private function resolve(Response $response): array
    {
        $json = $response->json();

        if (! $response->successful() || ! is_array($json)) {
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
     * Envelope de error funcional: nunca expone token, payload ni excepción.
     * El trace_id se registra en el canal `api_errors` para trazabilidad.
     */
    private function error(string $code, string $userMessage, mixed $raw): array
    {
        Log::channel('api_errors')->error('ApiClient error', [
            'code' => $code,
            'trace_id' => $raw['trace_id'] ?? null,
            'status' => $raw['status'] ?? null,
        ]);

        return [
            'success' => false,
            'code' => $code,
            'message' => $userMessage,
            'trace_id' => $raw['trace_id'] ?? null,
        ];
    }
}
