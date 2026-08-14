<?php

namespace Tests\Feature\Components;

use Tests\TestCase;

class VisualComponentsTest extends TestCase
{
    public function test_playground_route_renders_design_system_with_components(): void
    {
        $response = $this->get('/playground');

        $response->assertStatus(200);

        // Assert layout
        $response->assertSee('Design System Playground');

        // Assert KPI Card
        $response->assertSee('Venta del Día');

        // Assert Data Table & Badges & Currency
        $response->assertSee('Cliente de ejemplo A');
        $response->assertSee('$ 4,500.50');
        $response->assertSee('Activo');

        // Assert Filter Bar
        $response->assertSee('Limpiar');
        $response->assertSee('Consultar');

        // Assert Alerts
        $response->assertSee('Atención: Esta es una alerta de prueba');
    }

    public function test_playground_renders_chart_and_map_archetypes(): void
    {
        $response = $this->get('/playground');

        $response->assertStatus(200);

        // <x-chart>: canvas declarativo + data-attributes + resumen accesible
        $response->assertSee('data-rutx-chart', false);
        $response->assertSee('Ventas de la semana actual y anterior', false);
        $response->assertSee('Sin datos para el período seleccionado', false);

        // <x-map-view>: contenedor declarativo + marcadores con estado semántico
        $response->assertSee('data-rutx-map', false);
        $response->assertSee('Ruta Centro — Vendedor activo', false);
        $response->assertSee('Ruta Norte — Retraso en visita', false);
        $response->assertSee('Ruta Sur — Jornada detenida', false);
    }
}
