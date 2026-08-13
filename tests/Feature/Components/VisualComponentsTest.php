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
}
