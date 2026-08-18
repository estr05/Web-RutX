<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * SendNotificationRequest — emisión de avisos (contrato v2 §6.4, POST /notifications).
 *
 * Reglas:
 *   target_type → seller | route | zone.
 *   target_ids  → lista de enteros, entre 1 y 50 destinatarios (máximo del
 *                 contrato); sin duplicados.
 *   title       → texto 1..200 caracteres.
 *   body        → texto 1..1000 caracteres.
 *   priority    → normal | alta.
 *
 * El título y el cuerpo se limpian de HTML antes de persistir (se muestra el
 * texto plano): nunca se acepta HTML arbitrario. toApiPayload() construye el
 * payload exclusivamente desde validated() (guidelines §1.4).
 */
class SendNotificationRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'target_type' => ['required', 'string', 'in:seller,route,zone'],
            'target_ids' => ['required', 'array', 'min:1', 'max:50'],
            'target_ids.*' => ['integer', 'min:1', 'distinct'],
            'title' => ['required', 'string', 'min:1', 'max:200'],
            'body' => ['required', 'string', 'min:1', 'max:1000'],
            'priority' => ['nullable', 'string', 'in:normal,alta'],
        ];
    }

    /**
     * Payload de API con textos en texto plano y priority normal por defecto.
     *
     * @return array<string, mixed>
     */
    public function toApiPayload(): array
    {
        return [
            'target_type' => $this->validated('target_type'),
            'target_ids' => array_values(array_unique($this->validated('target_ids'))),
            'title' => trim(strip_tags((string) $this->validated('title'))),
            'body' => trim(strip_tags((string) $this->validated('body'))),
            'priority' => $this->validated('priority') ?? 'normal',
        ];
    }
}
