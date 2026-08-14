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
 * Conteos de configuración (Día 2):
 *   6 módulos · 30 vistas totales (2+3+3+15+4+3) · 35 rutas Laravel
 *   (30 de módulo + 1 raíz + 1 playground + 2 de storage/framework heredadas +
 *    1 health-check up).
 *
 * Middleware auth.session se activa en Día 5.
 * Las rutas scaffold están protegidas por guard de entorno: no se registran
 * en producción hasta que auth.session esté activo.
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

    public function test_navigation_config_has_exactly_thirty_views_total(): void
    {
        $modules   = config('navigation.modules', []);
        $viewCount = array_sum(array_map(
            fn (array $m) => count($m['views']),
            $modules,
        ));

        // customers:2 + products:3 + inventory:3 + sales:15 + routes:4 + settings:3 = 30
        $this->assertSame(30, $viewCount, 'La configuración de navegación debe declarar exactamente 30 vistas.');
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

    // -------------------------------------------------------------------------
    // Sidebar — botón semántico con aria-expanded (sin checkbox peer)
    // -------------------------------------------------------------------------

    public function test_sidebar_renders_toggle_button_with_aria_expanded(): void
    {
        $response = $this->get(route('sales.reports-graphics'));

        $response->assertStatus(200);
        // El sidebar usa un <button> semántico, no un checkbox oculto
        $response->assertSee('aria-expanded', false);
        $response->assertSee('sidebar-toggle-btn', false);
        $response->assertDontSee('sidebar-collapse-toggle', false);
    }

    // -------------------------------------------------------------------------
    // Barrera de despliegue: rutas scaffold no se registran en producción
    // -------------------------------------------------------------------------

    public function test_scaffold_routes_are_registered_in_non_production_environment(): void
    {
        // APP_ENV=testing en phpunit.xml — las rutas deben estar registradas
        $this->assertFalse(app()->isProduction(), 'Este test debe correr en entorno no productivo.');
        $this->assertTrue(
            \Illuminate\Support\Facades\Route::has('sales.reports-graphics'),
            'Las rutas scaffold deben registrarse en entornos no productivos.',
        );
    }
}
