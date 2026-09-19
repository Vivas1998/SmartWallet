<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_it_converts_euro_amounts_to_integer_cents(): void
    {
        $this->assertSame(123456, Money::toCents('1.234,56'));
        $this->assertSame(1000, Money::toCents('10'));
        $this->assertSame(5, Money::toCents('0.05'));
        $this->assertSame(-275, Money::toCents('-2,75'));
    }

    public function test_it_rejects_invalid_money_formats(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::toCents('12,345');
    }
}
