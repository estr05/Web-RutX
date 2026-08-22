<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Catálogo de metadatos de presentación visual para KPIs del Dashboard.
 *
 * Mapea estrictamente por la etiqueta contractual ('label') que entrega la API
 * del Sincronizador hacia su ropa visual (format, iconName, group).
 * No contiene ni inventa datos de negocio (guidelines §2.1, §5.2).
 */
final class DashboardKpiCatalog
{
    /**
     * @return array<string, array{format: string, iconName: string, group: string}>
     */
    public static function all(): array
    {
        return [
            'Venta total' => [
                'format' => 'currency',
                'iconName' => 'banknotes',
                'group' => 'primary',
            ],
            'Contado' => [
                'format' => 'currency',
                'iconName' => 'currency-dollar',
                'group' => 'primary',
            ],
            'Crédito' => [
                'format' => 'currency',
                'iconName' => 'credit-card',
                'group' => 'primary',
            ],
            'Cobranza' => [
                'format' => 'currency',
                'iconName' => 'clipboard-list',
                'group' => 'primary',
            ],
            'No ventas' => [
                'format' => 'integer',
                'iconName' => 'x-circle',
                'group' => 'secondary',
            ],
            'Entrega' => [
                'format' => 'currency',
                'iconName' => 'truck',
                'group' => 'secondary',
            ],
            'Gastos' => [
                'format' => 'currency',
                'iconName' => 'receipt-percent',
                'group' => 'secondary',
            ],
        ];
    }

    /**
     * Resuelve los metadatos visuales para una etiqueta dada, con fallback seguro.
     *
     * @return array{format: string, iconName: string|null, group: string}
     */
    public static function get(string $label): array
    {
        return self::all()[$label] ?? [
            'format' => 'currency',
            'iconName' => null,
            'group' => 'primary',
        ];
    }
}
