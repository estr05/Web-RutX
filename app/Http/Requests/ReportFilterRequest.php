<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ReportFilterRequest — filtros comunes de reportes y tablero.
 *
 * Campos (snake_case, igual que el contrato v2):
 *   range     → diario | semanal | mensual
 *   date_from → Y-m-d
 *   date_to   → Y-m-d, después o igual que date_from
 *   zone_id   → entero ≥ 1
 *   route_id  → entero ≥ 1
 *
 * toApiPayload() construye el payload exclusivamente desde validated()
 * (guidelines §1.4): nunca se reenvía $request->all() ni campos ocultos.
 */
class ReportFilterRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'range' => ['nullable', 'string', 'in:diario,semanal,mensual'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'zone_id' => ['nullable', 'integer', 'min:1'],
            'route_id' => ['nullable', 'integer', 'min:1'],
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
