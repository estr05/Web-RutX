<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * NotificationFilterRequest — filtros de la bandeja de avisos (contrato v2 §6.4).
 *
 * Campos (snake_case, igual que el contrato):
 *   status      → active | read | archived
 *   target_type → seller | route | zone
 *   page        → entero ≥ 1.
 *   per_page    → entero 1..100.
 *
 * toApiPayload() construye el payload exclusivamente desde validated()
 * (guidelines §1.4): nunca se reenvía $request->all() ni campos ocultos.
 */
class NotificationFilterRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', 'in:active,read,archived'],
            'target_type' => ['nullable', 'string', 'in:seller,route,zone'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Solo se mandan a la API los campos validados y con valor (guidelines §1.4).
     *
     * @return array<string, mixed>
     */
    public function toApiPayload(): array
    {
        return collect($this->validated())
            ->reject(fn (mixed $value): bool => is_null($value) || $value === '')
            ->all();
    }
}
