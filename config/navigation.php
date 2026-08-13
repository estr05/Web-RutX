<?php

return [
    'modules' => [
        'cliente' => [
            'label' => 'Cliente',
            'icon' => 'user-group',
            'permission' => 'module.cliente.access',
            'default_route' => 'cliente.index',
            'views' => [
                'cliente.index' => [
                    'label' => 'Clientes',
                    'icon' => 'users',
                    'permission' => 'cliente.read',
                ],
                'cliente.traspaso' => [
                    'label' => 'Traspaso de cliente',
                    'icon' => 'arrows-right-left',
                    'permission' => 'cliente.transfer',
                ],
            ],
        ],
        'producto' => [
            'label' => 'Producto',
            'icon' => 'cube',
            'permission' => 'module.producto.access',
            'default_route' => 'producto.index',
            'views' => [
                'producto.index' => [
                    'label' => 'Productos',
                    'icon' => 'cube',
                    'permission' => 'producto.read',
                ],
                'producto.precios' => [
                    'label' => 'Listas de precio',
                    'icon' => 'currency-dollar',
                    'permission' => 'producto.price.read',
                ],
                'producto.precios_zona' => [
                    'label' => 'Precios por zona',
                    'icon' => 'map-pin',
                    'permission' => 'producto.price.read',
                ],
            ],
        ],
        'inventario' => [
            'label' => 'Inventario',
            'icon' => 'archive-box',
            'permission' => 'module.inventario.access',
            'default_route' => 'inventario.ruta',
            'views' => [
                'inventario.ruta' => [
                    'label' => 'Inventario por ruta',
                    'icon' => 'truck',
                    'permission' => 'inventario.read',
                ],
                'inventario.rechazados' => [
                    'label' => 'Productos rechazados',
                    'icon' => 'x-circle',
                    'permission' => 'inventario.read',
                ],
                'inventario.mermas' => [
                    'label' => 'Mermas',
                    'icon' => 'trash',
                    'permission' => 'inventario.read',
                ],
            ],
        ],
        'venta' => [
            'label' => 'Venta',
            'icon' => 'shopping-cart',
            'permission' => 'module.venta.access',
            'default_route' => 'venta.reportes',
            'views' => [
                'venta.levantamiento' => [
                    'label' => 'Levantamiento',
                    'icon' => 'document-plus',
                    'permission' => 'venta.read',
                ],
                'venta.pedidos' => [
                    'label' => 'Pedidos',
                    'icon' => 'document-text',
                    'permission' => 'venta.read',
                ],
                'venta.cobranza' => [
                    'label' => 'Cobranza',
                    'icon' => 'banknotes',
                    'permission' => 'venta.read',
                ],
                'venta.utilidad' => [
                    'label' => 'Utilidad',
                    'icon' => 'presentation-chart-line',
                    'permission' => 'venta.read',
                ],
                'venta.deposito' => [
                    'label' => 'Depósito Venta',
                    'icon' => 'arrow-down-tray',
                    'permission' => 'venta.read',
                ],
                'venta.gasto' => [
                    'label' => 'Nuevo Gasto Operativo',
                    'icon' => 'receipt-percent',
                    'permission' => 'venta.read',
                ],
                'venta.reporte_cliente' => [
                    'label' => 'Reporte de Ventas por Cliente',
                    'icon' => 'chart-pie',
                    'permission' => 'reports.read',
                ],
                'venta.rentabilidad' => [
                    'label' => 'Reporte Rentabilidad por Ruta',
                    'icon' => 'chart-bar',
                    'permission' => 'reports.read',
                ],
                'venta.mayor_venta' => [
                    'label' => 'Clientes con Mayor Venta',
                    'icon' => 'star',
                    'permission' => 'reports.read',
                ],
                'venta.productos_rechazados' => [
                    'label' => 'Productos Rechazados',
                    'icon' => 'x-mark',
                    'permission' => 'reports.read',
                ],
                'venta.preventa' => [
                    'label' => 'Reporte Preventa Entrega',
                    'icon' => 'clipboard-document-check',
                    'permission' => 'reports.read',
                ],
                'venta.clientes_pendientes' => [
                    'label' => 'Clientes Pendientes',
                    'icon' => 'clock',
                    'permission' => 'reports.read',
                ],
                'venta.visor' => [
                    'label' => 'Visor',
                    'icon' => 'eye',
                    'permission' => 'venta.read',
                ],
                'venta.reportes' => [
                    'label' => 'Reportes y Gráficas',
                    'icon' => 'chart-pie',
                    'permission' => 'reports.read',
                ],
                'venta.globales' => [
                    'label' => 'Reportes Globales',
                    'icon' => 'globe-americas',
                    'permission' => 'reports.read',
                ],
            ],
        ],
        'ruta' => [
            'label' => 'Ruta',
            'icon' => 'truck',
            'permission' => 'module.ruta.access',
            'default_route' => 'ruta.mapa',
            'views' => [
                'ruta.mapa' => [
                    'label' => 'Mapa en tiempo real',
                    'icon' => 'map',
                    'permission' => 'route.monitor',
                ],
                'ruta.jornada' => [
                    'label' => 'Jornada del día',
                    'icon' => 'sun',
                    'permission' => 'route.monitor',
                ],
                'ruta.agenda' => [
                    'label' => 'Agenda',
                    'icon' => 'calendar-days',
                    'permission' => 'agenda.read',
                ],
                'ruta.kilometraje' => [
                    'label' => 'Kilometraje',
                    'icon' => 'forward',
                    'permission' => 'route.monitor',
                ],
            ],
        ],
        'configuracion' => [
            'label' => 'Configuración',
            'icon' => 'cog-6-tooth',
            'permission' => 'module.configuracion.access',
            'default_route' => 'configuracion.usuarios',
            'views' => [
                'configuracion.usuarios' => [
                    'label' => 'Usuarios',
                    'icon' => 'users',
                    'permission' => 'config.users.read',
                ],
                'configuracion.roles' => [
                    'label' => 'Roles',
                    'icon' => 'shield-check',
                    'permission' => 'config.roles.read',
                ],
                'configuracion.zonas' => [
                    'label' => 'Zonas',
                    'icon' => 'map',
                    'permission' => 'config.zones.read',
                ],
            ],
        ],
    ],
];
