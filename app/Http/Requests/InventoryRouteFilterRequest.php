<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * InventoryRouteFilterRequest — filtros del piloto de inventario por ruta
 * (contrato v2 §6.4).
 *
 * Campos (snake_case, igual que el contrato):
 *   route_id  → entero ≥ 1 (≡ VENDEDOR_ID); requerido por el piloto.
 *   as_of     → periodo YYYY-MM (ISO 8601).
 *   zone_id   → entero ≥ 1.
 *   page      → entero ≥ 1.
 *   per_page  → entero 1..100.
 *
 * toApiPayload() construye el payload exclusivamente desde validated()
 * (guidelines §1.4): nunca se reenvía $request->all() ni campos ocultos.
 */
class InventoryRouteFilterRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'route_id' => ['required', 'integer', 'min:1'],
            'as_of' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'zone_id' => ['nullable', 'integer', 'min:1'],
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
