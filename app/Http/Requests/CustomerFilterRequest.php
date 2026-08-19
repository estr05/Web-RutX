<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * CustomerFilterRequest — filtros del catálogo de clientes (contrato v2 §3.2).
 *
 * Campos (snake_case, igual que el contrato):
 *   search    → texto libre (nombre o código)
 *   zone_id   → entero ≥ 1
 *   route_id  → entero ≥ 1 (≡ VENDEDOR_ID)
 *   status    → A | B
 *   page      → entero ≥ 1
 *   per_page  → entero 1..100
 *
 * toApiPayload() construye el payload exclusivamente desde validated()
 * (guidelines §1.4): nunca se reenvía $request->all() ni campos ocultos.
 */
class CustomerFilterRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:200'],
            'zone_id' => ['nullable', 'integer', 'min:1'],
            'route_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'in:A,B'],
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
