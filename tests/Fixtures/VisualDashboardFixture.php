<?php

declare(strict_types=1);

namespace Tests\Fixtures;

final class VisualDashboardFixture
{
    /**
     * Datos representativos no-cero para verificar renderizado visual, desgloses y overflow.
     */
    public static function summary(): array
    {
        return [
            'success' => true,
            'data' => [
                'kpi' => [
                    [
                        'label' => 'Venta total',
                        'value' => 669558.99,
                        'delta' => '+5.2%',
                        'status' => 'success',
                        'sub_label' => 'Contado + Crédito',
                        'sub_value' => null,
                        'details' => [],
                    ],
                    [
                        'label' => 'Contado',
                        'value' => 450200.00,
                        'delta' => '+3.1%',
                        'status' => 'success',
                        'sub_label' => null,
                        'sub_value' => null,
                        'details' => [],
                    ],
                    [
                        'label' => 'Crédito',
                        'value' => 219358.99,
                        'delta' => '-1.0%',
                        'status' => 'warning',
                        'sub_label' => null,
                        'sub_value' => null,
                        'details' => [],
                    ],
                    [
                        'label' => 'Cobranza',
                        'value' => 98500.00,
                        'delta' => null,
                        'status' => 'info',
                        'sub_label' => null,
                        'sub_value' => null,
                        'details' => [],
                    ],
                    [
                        'label' => 'No ventas',
                        'value' => 7,
                        'delta' => '-2',
                        'status' => 'error',
                        'sub_label' => null,
                        'sub_value' => null,
                        'details' => [],
                    ],
                    [
                        'label' => 'Entrega',
                        'value' => 112000.00,
                        'delta' => null,
                        'status' => 'warning',
                        'sub_label' => null,
                        'sub_value' => null,
                        'details' => [
                            ['label' => 'Contado', 'value' => 74000.00],
                            ['label' => 'Crédito', 'value' => 38000.00],
                        ],
                    ],
                    [
                        'label' => 'Gastos',
                        'value' => 13500.00,
                        'delta' => null,
                        'status' => 'neutral',
                        'sub_label' => null,
                        'sub_value' => null,
                        'details' => [],
                    ],
                ],
                'meta' => [
                    'currency' => 'MXN',
                    'last_sync_at' => '2026-08-21T18:00:00Z',
                ],
            ],
        ];
    }

    public static function report(): array
    {
        return [
            'success' => true,
            'data' => [
                'totals' => [
                    'sales_amount' => 669558.99,
                    'pieces' => 1240,
                    'currency' => 'MXN',
                ],
                'by_route' => [
                    [
                        'route_name' => 'Ruta Norte',
                        'pieces' => 450,
                        'cash_amount' => 150000.00,
                        'credit_amount' => 80000.00,
                        'total_amount' => 230000.00,
                    ],
                    [
                        'route_name' => 'Ruta Sur',
                        'pieces' => 380,
                        'cash_amount' => 180000.00,
                        'credit_amount' => 60000.00,
                        'total_amount' => 240000.00,
                    ],
                    [
                        'route_name' => 'Ruta Centro',
                        'pieces' => 410,
                        'cash_amount' => 120200.00,
                        'credit_amount' => 79358.99,
                        'total_amount' => 199558.99,
                    ],
                ],
                'status' => 'ok',
            ],
        ];
    }
}
