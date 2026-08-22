<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\DashboardKpiCatalog;
use PHPUnit\Framework\TestCase;

class DashboardKpiCatalogTest extends TestCase
{
    public function test_all_contains_seven_business_kpis(): void
    {
        $catalog = DashboardKpiCatalog::all();

        $this->assertCount(7, $catalog);
        $this->assertArrayHasKey('Venta total', $catalog);
        $this->assertArrayHasKey('Contado', $catalog);
        $this->assertArrayHasKey('Crédito', $catalog);
        $this->assertArrayHasKey('Cobranza', $catalog);
        $this->assertArrayHasKey('No ventas', $catalog);
        $this->assertArrayHasKey('Entrega', $catalog);
        $this->assertArrayHasKey('Gastos', $catalog);
    }

    public function test_no_ventas_is_formatted_as_integer(): void
    {
        $meta = DashboardKpiCatalog::get('No ventas');

        $this->assertSame('integer', $meta['format']);
        $this->assertSame('x-circle', $meta['iconName']);
        $this->assertSame('secondary', $meta['group']);
    }

    public function test_monetary_kpis_are_formatted_as_currency(): void
    {
        $this->assertSame('currency', DashboardKpiCatalog::get('Venta total')['format']);
        $this->assertSame('currency', DashboardKpiCatalog::get('Contado')['format']);
        $this->assertSame('currency', DashboardKpiCatalog::get('Crédito')['format']);
        $this->assertSame('currency', DashboardKpiCatalog::get('Cobranza')['format']);
        $this->assertSame('currency', DashboardKpiCatalog::get('Entrega')['format']);
        $this->assertSame('currency', DashboardKpiCatalog::get('Gastos')['format']);
    }

    public function test_unknown_label_returns_safe_fallback(): void
    {
        $meta = DashboardKpiCatalog::get('KPI Inexistente');

        $this->assertSame('currency', $meta['format']);
        $this->assertNull($meta['iconName']);
        $this->assertSame('primary', $meta['group']);
    }
}
