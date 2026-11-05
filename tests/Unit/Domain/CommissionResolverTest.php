<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Tests\Unit\Domain;

use Cheppi\Sellers\Domain\Commission\CommissionResolver;
use Cheppi\Sellers\Domain\Commission\CommissionSnapshot;
use Cheppi\Sellers\Domain\Money\Money;
use Cheppi\Sellers\Domain\Rules\BusinessRules;
use Cheppi\Sellers\Tests\TestCase;

final class CommissionResolverTest extends TestCase
{
    private function resolver(): CommissionResolver
    {
        $data = BusinessRules::defaults()->toArray();
        $data['category_commissions_bps'] = [12 => 1000, 30 => 600];
        return new CommissionResolver(BusinessRules::fromArray($data));
    }

    public function testVendorOverrideWins(): void
    {
        $s = $this->resolver()->resolve(300, 'pro', [12]);
        $this->assertSame(300, $s->bps);
        $this->assertSame(CommissionSnapshot::SOURCE_VENDOR, $s->source);
    }

    public function testLowestCategoryRateBeatsPlan(): void
    {
        $s = $this->resolver()->resolve(null, 'emprende', [12, 30, 99]);
        $this->assertSame(600, $s->bps);
        $this->assertSame(CommissionSnapshot::SOURCE_CATEGORY, $s->source);
    }

    public function testPlanRateWhenNoCategoryRule(): void
    {
        $s = $this->resolver()->resolve(null, 'pro', [99]);
        $this->assertSame(500, $s->bps);
        $this->assertSame(CommissionSnapshot::SOURCE_PLAN, $s->source);
    }

    public function testFallsBackToDefaultForUnknownPlan(): void
    {
        $s = $this->resolver()->resolve(null, 'retirado', []);
        $this->assertSame(800, $s->bps);
        $this->assertSame(CommissionSnapshot::SOURCE_DEFAULT, $s->source);
    }

    public function testCommissionAmount(): void
    {
        $amount = CommissionResolver::commissionFor(Money::ofDecimal('100'), new CommissionSnapshot(800, 'plan'));
        $this->assertSame('8.00', $amount->toDecimal());
    }
}
