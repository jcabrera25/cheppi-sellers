<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Tests\Unit\Domain;

use Cheppi\Sellers\Domain\Money\Money;
use Cheppi\Sellers\Domain\Shipping\DeliveryZone;
use Cheppi\Sellers\Domain\Shipping\ShipmentRequest;
use Cheppi\Sellers\Domain\Shipping\VendorDeliveryProvider;
use Cheppi\Sellers\Domain\Vendor\VendorStatus;
use Cheppi\Sellers\Tests\TestCase;

final class ShippingAndVendorTest extends TestCase
{
    private function provider(): VendorDeliveryProvider
    {
        return new VendorDeliveryProvider([
            new DeliveryZone('Lima Moderna', ['san-isidro', 'miraflores'], Money::ofDecimal('8'), 1, Money::ofDecimal('150')),
            new DeliveryZone('Lima Norte', ['los-olivos'], Money::ofDecimal('12'), 2),
        ]);
    }

    public function testQuotesMatchingZone(): void
    {
        $quote = $this->provider()->quote(new ShipmentRequest(7, 'los-olivos', Money::ofDecimal('80')));
        $this->assertSame('12.00', $quote?->cost->toDecimal());
        $this->assertSame(2, $quote?->etaDays);
        $this->assertSame('vendor_delivery', $quote?->providerCode);
    }

    public function testFreeShippingThreshold(): void
    {
        $quote = $this->provider()->quote(new ShipmentRequest(7, 'miraflores', Money::ofDecimal('150')));
        $this->assertSame(0, $quote?->cost->cents);
    }

    public function testNoQuoteOutsideZones(): void
    {
        $this->assertSame(null, $this->provider()->quote(new ShipmentRequest(7, 'arequipa', Money::ofDecimal('80'))));
    }

    public function testVendorStatusTransitions(): void
    {
        $this->assertTrue(VendorStatus::Pending->canTransitionTo(VendorStatus::Approved));
        $this->assertFalse(VendorStatus::Rejected->canTransitionTo(VendorStatus::Approved));
        $this->assertTrue(VendorStatus::Approved->canSell());
        $this->assertFalse(VendorStatus::Suspended->canSell());
    }
}
