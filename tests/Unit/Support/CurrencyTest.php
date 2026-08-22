<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class CurrencyTest extends TestCase
{
    public function test_formats_positive_amount(): void
    {
        $this->assertSame('$ 1,234.56', Money::format(1234.56));
    }

    public function test_formats_zero_amount(): void
    {
        $this->assertSame('$ 0.00', Money::format(0.0));
    }

    public function test_formats_negative_amount(): void
    {
        $this->assertSame('-$ 320.00', Money::format(-320.0));
    }

    public function test_formats_large_amount_without_scientific_notation(): void
    {
        $this->assertSame('$ 669,558.99', Money::format(669558.99));
    }
}
