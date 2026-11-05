<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Tests\Unit\Domain;

use Cheppi\Sellers\Domain\Money\Money;
use Cheppi\Sellers\Tests\TestCase;
use InvalidArgumentException;

final class MoneyTest extends TestCase
{
    public function testParsesDecimalsWithoutFloatDrift(): void
    {
        $this->assertSame(1990, Money::ofDecimal('19.90')->cents);
        $this->assertSame(30, Money::ofDecimal('0.1')->add(Money::ofDecimal('0.2'))->cents);
    }

    public function testPercentageRoundsHalfUp(): void
    {
        // 8% of S/ 19.90 = 1.592 → 1.59
        $this->assertSame(159, Money::ofDecimal('19.90')->percentage(800)->cents);
        // 5% of S/ 0.10 = 0.005 → 0.01
        $this->assertSame(1, Money::ofCents(10)->percentage(500)->cents);
        // Symmetric for negatives
        $this->assertSame(-1, Money::ofCents(-10)->percentage(500)->cents);
    }

    public function testFormatsDecimal(): void
    {
        $this->assertSame('-3.05', Money::ofCents(-305)->toDecimal());
        $this->assertSame('0.00', Money::zero()->toDecimal());
    }

    public function testRejectsCurrencyMismatch(): void
    {
        $this->assertThrows(InvalidArgumentException::class, fn () => Money::ofCents(1, 'PEN')->add(Money::ofCents(1, 'USD')));
    }
}
