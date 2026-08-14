<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * NavigationShellTest
 *
 * Verifica que el chasis de navegación del Día 2 funciona correctamente:
 * - Ruta activa → módulo activo → sidebar filtrado → breadcrumb.
 * - Recorrido: Clientes → Venta → Ruta.
 * - La entrada inicial es Venta · Reportes y Gráficas.
 * - config/navigation.php no depende de closures ni helpers de request
 *   (compatible con config:cache).
 *
 * Nota: Middleware auth.session no está activado en Día 2 (Día 5).
 * Las rutas scaffold son accesibles sin autenticación en esta fase.
 */
class NavigationShellTest extends TestCase
{
    // -------------------------------------------------------------------------
    // config/navigation.php — seguro para config:cache
    // -------------------------------------------------------------------------

    public function test_navigation_config_is_cacheable_and_contains_no_closures(): void
    {
        $config = config('navigation.modules', []);

        $this->assertNotEmpty($config, 'config/navigation.php no debe estar vacío.');

        foreach ($config as $moduleKey => $module) {
            $this->assertIsString($module['label'], "label de módulo '{$moduleKey}' debe ser string.");
            $this->assertIsString($module['default_route'], "default_route de módulo '{$moduleKey}' debe ser string.");
            $this->assertIsArray($module['views'], "views de módulo '{$moduleKey}' debe ser array.");

            foreach ($module['views'] as $routeName => $view) {
                $this->assertIsString($routeName, 'La clave de vista debe ser string (nombre de ruta).');
                $this->assertIsString($view['label'], "label de vista '{$routeName}' debe ser string.");
            }
        }
    }

    public function test_navigation_config_has_exactly_six_modules(): void
    {
        $modules = config('navigation.modules', []);

        $this->assertCount(6, $modules, 'Deben existir exactamente 6 módulos en navigation config.');
    }

    public function test_navigation_module_keys_are_in_english(): void
    {
        $modules = config('navigation.modules', []);
        $keys    = array_keys($modules);

        $expectedKeys = ['customers', 'products', 'inventory', 'sales', 'routes', 'settings'];
        $this->assertEqualsCanonicalizing($expectedKeys, $keys, 'Las claves de módulo deben estar en inglés.');
    }

    public function test_sales_default_route_is_reports_graphics(): void
    {
        $sales = config('navigation.modules.sales');

        $this->assertSame(
            'sales.reports-graphics',
            $sales['default_route'],
            'La entrada inicial del chasis debe ser sales.reports-graphics.',
        );
    }

    // -------------------------------------------------------------------------
    // Rutas scaffold: registro y nombre correcto
    // -------------------------------------------------------------------------

    public function test_all_module_default_routes_are_registered(): void
    {
        $modules = config('navigation.modules', []);

        foreach ($modules as $moduleKey => $module) {
            $this->assertTrue(
                \Illuminate\Support\Facades\Route::has($module['default_route']),
                "La ruta por defecto '{$module['default_route']}' del módulo '{$moduleKey}' no está registrada.",
            );
        }
    }

    public function test_all_view_routes_are_registered(): void
    {
        $modules = config('navigation.modules', []);

        foreach ($modules as $moduleKey => $module) {
            foreach ($module['views'] as $routeName => $view) {
                $this->assertTrue(
                    \Illuminate\Support\Facades\Route::has($routeName),
                    "La ruta '{$routeName}' del módulo '{$moduleKey}' no está registrada.",
                );
            }
        }
    }

    // -------------------------------------------------------------------------
    // Navegación contextual: ruta activa → módulo activo → breadcrumb
    // -------------------------------------------------------------------------

    public function test_entry_point_redirects_to_sales_reports_graphics(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('sales.reports-graphics'));
    }

    public function test_sales_reports_graphics_renders_with_venta_in_title(): void
    {
        $response = $this->get(route('sales.reports-graphics'));

        $response->assertStatus(200);
        // El título del layout incluye el módulo derivado desde navigation config
        $response->assertSee('Venta');
        $response->assertSee('Reportes y Gráficas');
    }

    public function test_customers_index_renders_with_cliente_breadcrumb(): void
    {
        $response = $this->get(route('customers.index'));

        $response->assertStatus(200);
        $response->assertSee('Cliente');
        $response->assertSee('Clientes');
    }

    public function test_routes_map_renders_with_ruta_breadcrumb(): void
    {
        $response = $this->get(route('routes.map'));

        $response->assertStatus(200);
        $response->assertSee('Ruta');
        $response->assertSee('Mapa en tiempo real');
    }

    // -------------------------------------------------------------------------
    // Recorrido Cliente → Venta → Ruta
    // -------------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\DataProvider('moduleNavigationProvider')]
    public function test_module_scaffold_renders_correctly(
        string $routeName,
        string $expectedModuleLabel,
        string $expectedViewLabel,
    ): void {
        $response = $this->get(route($routeName));

        $response->assertStatus(200);
        $response->assertSee($expectedModuleLabel);
        $response->assertSee($expectedViewLabel);
    }

    public static function moduleNavigationProvider(): array
    {
        return [
            'clientes index'       => ['customers.index',          'Cliente',       'Clientes'],
            'clientes traspaso'    => ['customers.transfer',        'Cliente',       'Traspaso de cliente'],
            'venta reportes'       => ['sales.reports-graphics',    'Venta',         'Reportes y Gráficas'],
            'venta pedidos'        => ['sales.orders',              'Venta',         'Pedidos'],
            'ruta mapa'            => ['routes.map',                'Ruta',          'Mapa en tiempo real'],
            'ruta agenda'          => ['routes.agenda',             'Ruta',          'Agenda'],
            'inventario ruta'      => ['inventory.routes',          'Inventario',    'Inventario por ruta'],
            'productos index'      => ['products.index',            'Producto',      'Productos'],
            'configuracion users'  => ['settings.users',            'Configuración', 'Usuarios'],
        ];
    }

    // -------------------------------------------------------------------------
    // Verificar que el pill NO dice "CONECTADO" (sin ApiClient)
    // -------------------------------------------------------------------------

    public function test_topbar_does_not_claim_connected_status(): void
    {
        $response = $this->get(route('sales.reports-graphics'));

        $response->assertStatus(200);
        // Sin ApiClient, el pill no debe mostrar "CONECTADO"
        $response->assertDontSee('CONECTADO');
        // Debe mostrar estado neutral
        $response->assertSee('Sincronización');
        $response->assertSee('pendiente');
    }

    // -------------------------------------------------------------------------
    // Verificar que navigation config tiene un solo listado (sin arrays duplicados)
    // -------------------------------------------------------------------------

    public function test_navigation_views_do_not_contain_modules_from_other_modules(): void
    {
        // Cada vista de "sales" no debe aparecer en "customers"
        $sales     = config('navigation.modules.sales.views', []);
        $customers = config('navigation.modules.customers.views', []);

        $intersection = array_intersect_key($sales, $customers);
        $this->assertEmpty($intersection, 'Las vistas de módulos distintos no deben solaparse.');
    }
}
