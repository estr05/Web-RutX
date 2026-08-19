<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * AgendaQueryRequest — filtros del tablero y panel lateral (contrato v2 §6.2).
 *
 * Filtra el tablero semanal (GET /agendas) y el panel de no asignados
 * (GET /agendas/unassigned-customers). Los filtros son opcionales; la API
 * devuelve el rango por defecto cuando se omiten.
 *
 * Reglas:
 *   zone_id    → entero positivo; acota la zona del tablero.
 *   route_id   → entero positivo; equivale a seller_id (contrato §12.1).
 *   date_from  → fecha ISO 8601 (YYYY-MM-DD); inicio del periodo.
 *   date_to    → fecha ISO 8601; fin del periodo; debe ser >= date_from.
 *   search     → texto libre 1..100 caracteres (solo para unassigned).
 *   page       → entero >= 1; por defecto 1.
 *   per_page   → entero 1..100; por defecto 25.
 *
 * toApiFilters() construye el payload exclusivamente desde validated()
 * (guidelines §1.4): nunca se reenvía el estado completo del request.
 */
class AgendaQueryRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'zone_id' => ['nullable', 'integer', 'min:1'],
            'route_id' => ['nullable', 'integer', 'min:1'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Filtros para la API; omite valores nulos para no sobrecargar el query.
     *
     * @return array<string, mixed>
     */
    public function toApiFilters(): array
    {
        return array_filter(
            [
                'zone_id' => $this->validated('zone_id'),
                'route_id' => $this->validated('route_id'),
                'date_from' => $this->validated('date_from'),
                'date_to' => $this->validated('date_to'),
                'search' => $this->validated('search'),
                'page' => $this->validated('page') ?? 1,
                'per_page' => $this->validated('per_page') ?? 25,
            ],
            fn ($v) => $v !== null,
        );
    }
}
