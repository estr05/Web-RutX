<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * BatchAssignmentRequest — comando batch de agenda (contrato v2 §6.2).
 *
 * Valida el payload de PATCH /api/v2/web/agendas/assignments:batch.
 * Siguiendo el patrón de SendNotificationRequest: construye el payload
 * exclusivamente desde validated() via toApiPayload() (guidelines §1.4).
 *
 * Reglas:
 *   schedule_version  → entero >= 0; versión actual del tablero para detectar
 *                        edición concurrente (409 SCHEDULE_VERSION_CONFLICT).
 *   assignments       → array de 1..50 movimientos (límite del contrato).
 *   assignments.*.customer_id → entero positivo; cliente que se mueve.
 *   assignments.*.action      → "assign" | "move" | "remove".
 *   assignments.*.seller_id   → entero positivo; obligatorio para assign/move,
 *                                nulo para remove.
 *   assignments.*.agenda_date → fecha ISO 8601; obligatoria para assign/move,
 *                                nula para remove.
 *
 * No se aceptan propiedades arbitrarias: el payload se construye campo a campo
 * desde validated() y nunca se reenvía el estado completo del componente
 * Livewire (guidelines §1.4, §1.5).
 */
class BatchAssignmentRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'schedule_version' => ['required', 'integer', 'min:0'],
            'assignments' => ['required', 'array', 'min:1', 'max:50'],
            'assignments.*.customer_id' => ['required', 'integer', 'min:1'],
            'assignments.*.action' => ['required', 'string', 'in:assign,move,remove'],
            'assignments.*.seller_id' => ['nullable', 'integer', 'min:1'],
            'assignments.*.agenda_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * Payload de API construido campo a campo desde validated().
     * Normaliza cada asignación: remove no envía seller_id ni agenda_date.
     *
     * @return array<string, mixed>
     */
    public function toApiPayload(): array
    {
        $validated = $this->validated();

        $assignments = array_map(function (array $item): array {
            $entry = [
                'customer_id' => (int) $item['customer_id'],
                'action' => $item['action'],
            ];

            if ($item['action'] !== 'remove') {
                $entry['seller_id'] = isset($item['seller_id']) ? (int) $item['seller_id'] : null;
                $entry['agenda_date'] = $item['agenda_date'] ?? null;
            }

            return $entry;
        }, $validated['assignments']);

        return [
            'schedule_version' => (int) $validated['schedule_version'],
            'assignments' => $assignments,
        ];
    }
}
