<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    /**
     * A basic unit test example.
     */
    public function test_it_formats_toman_amounts_with_ascii_thousands_separators(): void
    {
        $this->assertSame('1,180,000', Money::format(1180000));
        $this->assertSame('1,050,000 تومان', Money::toman(1050000));
        $this->assertSame('0', Money::format(null));
    }

    public function test_it_normalizes_supported_digit_scripts_and_separators(): void
    {
        $this->assertSame(1180000, Money::normalize('۱٬۱۸۰٬۰۰۰'));
        $this->assertSame(950000, Money::normalize('٩٥٠,٠٠٠'));
        $this->assertSame(1050000, Money::normalize('1,050,000'));
        $this->assertNull(Money::normalize(''));
    }

    public function test_it_rejects_invalid_monetary_values(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::normalize('1.2m');
    }

    public function test_it_rejects_malformed_grouping(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::normalize('1,18,000');
    }
}
