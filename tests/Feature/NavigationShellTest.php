<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * NavigationShellTest
 *
 * Verifica que el chasis de navegación funciona correctamente:
 * - Ruta activa → módulo activo → sidebar filtrado → breadcrumb.
 * - Recorrido: Clientes → Venta → Ruta.
 * - La entrada inicial es Venta · Reportes y Gráficas (venta.reportes).
 * - config/navigation.php no depende de closures ni helpers de request (compatible con config:cache).
 * - Los módulos de negocio van protegidos por auth.session, así que las vistas
 *   se prueban con un token de sesión cifrada.
 *
 * Conteos de configuración (Sprint 3 · Etapa 2):
 *   6 módulos · 16 vistas totales (2+3+3+3+2+3)
 */
class NavigationShellTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // venta.reportes ya renderiza el componente Livewire de Reportes y
        // Gráficas: modo stub para que el render del chasis no intente
        // llamadas HTTP reales durante las pruebas de navegación.
        config()->set('services.api_web.stubs_enabled', true);

        // Los componentes funcionales del Sprint 4 (Clientes, Inventario,
        // Notificaciones) consultan la API v2 desde su primer render: se
        // impide cualquier llamada HTTP real durante el recorrido del chasis.
        Http::fake();
    }

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
                $this->assertIsString($view['icon'], "icon de vista '{$routeName}' debe ser string.");
            }
        }
    }

    public function test_navigation_config_has_exactly_six_modules(): void
    {
        $modules = config('navigation.modules', []);

        $this->assertCount(6, $modules, 'Deben existir exactamente 6 módulos en navigation config.');
    }

    public function test_navigation_module_keys_match_route_prefixes(): void
    {
        $modules = config('navigation.modules', []);
        $keys = array_keys($modules);

        // Las claves siguen el prefijo de los nombres de ruta (las usa el sidebar
        // con str_starts_with). Venta y Ruta usan los nombres de módulo del Sprint 3.
        $expectedKeys = ['customers', 'products', 'inventory', 'venta', 'ruta', 'settings'];
        $this->assertEqualsCanonicalizing($expectedKeys, $keys, 'Las claves de módulo deben coincidir con los prefijos de ruta.');
    }

    public function test_venta_default_route_is_reportes(): void
    {
        $venta = config('navigation.modules.venta');

        $this->assertSame(
            'venta.reportes',
            $venta['default_route'],
            'La entrada inicial del chasis debe ser venta.reportes.',
        );
    }

    public function test_navigation_config_has_exactly_sixteen_views_total(): void
    {
        $modules = config('navigation.modules', []);
        $viewCount = array_sum(array_map(
            fn (array $m) => count($m['views']),
            $modules,
        ));

        // customers:2 + products:3 + inventory:3 + venta:3 + ruta:2 + settings:3 = 16
        $this->assertSame(16, $viewCount, 'La configuración de navegación debe declarar exactamente 16 vistas.');
    }

    // -------------------------------------------------------------------------
    // Rutas scaffold: registro y nombre correcto
    // -------------------------------------------------------------------------

    public function test_all_module_default_routes_are_registered(): void
    {
        $modules = config('navigation.modules', []);

        foreach ($modules as $moduleKey => $module) {
            $this->assertTrue(
                Route::has($module['default_route']),
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
                    Route::has($routeName),
                    "La ruta '{$routeName}' del módulo '{$moduleKey}' no está registrada.",
                );
            }
        }
    }

    // -------------------------------------------------------------------------
    // Cobertura completa: Recorrer las 16 vistas declaradas (sesión autenticada)
    // -------------------------------------------------------------------------

    public function test_every_declared_view_renders_its_navigation_context(): void
    {
        // Las rutas de módulo van protegidas por auth.session (Sprint 3) y por
        // permission:<claim> (Sprint 4): la sesión de prueba declara los
        // permisos de todas las vistas para ejercitar el chasis de navegación.
        $this->session([
            'api_token' => 'web-token-test',
            'permissions' => [
                'customers.read',
                'products.read',
                'products.price.read',
                'inventory.read',
                'reports.read',
                'routes.monitor',
                'config.users.read',
                'config.roles.read',
                'config.zones.read',
                'notifications.read',
            ],
        ]);

        foreach (config('navigation.modules', []) as $module) {
            foreach ($module['views'] as $routeName => $view) {
                $response = $this->get(route($routeName));

                $response->assertStatus(200);
                $response->assertSee($module['label']);
                $response->assertSee($view['label']);
            }
        }
    }

    public function test_entry_point_redirects_to_venta_reportes(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/venta');
    }

    // -------------------------------------------------------------------------
    // Sidebar — marcado accesible, toggle button y data-navigation-icon
    // -------------------------------------------------------------------------

    public function test_sidebar_renders_accessible_markup_and_icons(): void
    {
        $this->session([
            'api_token' => 'web-token-test',
            'permissions' => [
                'reports.read',
                'routes.monitor',
            ],
        ]);

        $response = $this->get(route('venta.reportes'));

        $response->assertStatus(200);
        $response->assertSee('data-sidebar', false);
        $response->assertSee('data-sidebar-toggle', false);
        $response->assertSee('aria-controls="app-sidebar"', false);
        $response->assertSee('aria-expanded="true"', false);
        $response->assertSee('bg-rutx-primary-dark', false);
        $response->assertSee('data-navigation-icon="chart-pie"', false);
    }

    public function test_sidebar_renders_icon_for_each_view_of_active_module(): void
    {
        $this->session([
            'api_token' => 'web-token-test',
            'permissions' => [
                'reports.read',
                'routes.monitor',
            ],
        ]);

        $venta = config('navigation.modules.venta.views');

        $response = $this->get(route('venta.reportes'));
        $response->assertStatus(200);

        foreach ($venta as $view) {
            $expectedIcon = $view['icon'];
            $response->assertSee("data-navigation-icon=\"{$expectedIcon}\"", false);
        }
    }

    // -------------------------------------------------------------------------
    // Regla de arquitectura: los wrappers no deben pasar labels manualmente
    // -------------------------------------------------------------------------

    public function test_module_wrappers_do_not_override_navigation_labels(): void
    {
        $modulesDir = resource_path('views/modules');
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($modulesDir),
        );

        foreach ($files as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            $this->assertStringNotContainsString(
                'module-label=',
                $content,
                "El wrapper {$file->getFilename()} no debe pasar module-label manualmente.",
            );
            $this->assertStringNotContainsString(
                'view-label=',
                $content,
                "El wrapper {$file->getFilename()} no debe pasar view-label manualmente.",
            );
        }
    }
}
