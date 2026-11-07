<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Tests\Unit\Domain;

use Cheppi\Sellers\Domain\Money\Money;
use Cheppi\Sellers\Domain\Shipping\DeliveryZone;
use Cheppi\Sellers\Domain\Shipping\LimaDistricts;
use Cheppi\Sellers\Domain\Shipping\ShipmentRequest;
use Cheppi\Sellers\Domain\Shipping\VendorDeliveryProvider;
use Cheppi\Sellers\Domain\Shipping\ZoneValidationException;
use Cheppi\Sellers\Tests\TestCase;

final class DeliveryZoneTest extends TestCase
{
    public function testDistrictNamesFromCheckout(): void
    {
        $this->assertSame('jesus-maria', LimaDistricts::fromName('Jesús María'));
        $this->assertSame('jesus-maria', LimaDistricts::fromName('jesus maria '));
        $this->assertSame('surco', LimaDistricts::fromName('Surco'));
        $this->assertSame('surco', LimaDistricts::fromName('Santiago de Surco'));
        $this->assertSame('lima', LimaDistricts::fromName('Lima'));
        $this->assertSame('miraflores', LimaDistricts::fromName('miraflores'));
        $this->assertSame(null, LimaDistricts::fromName('Arequipa'));
    }

    public function testZonesFromFormAndQuotes(): void
    {
        $zones = DeliveryZone::listFromInput([
            ['name' => 'Resto de Lima', 'districts' => ['*'], 'cost' => '15', 'eta_days' => '3', 'free_from' => ''],
            ['name' => 'Cerca', 'districts' => ['barranco', 'miraflores', 'nope'], 'cost' => '8,50', 'eta_days' => '1', 'free_from' => '150'],
            ['name' => '', 'districts' => [], 'cost' => '', 'eta_days' => ''], // empty row ignored
        ]);
        $this->assertSame(2, count($zones));
        $this->assertSame('Cerca', $zones[0]->name, 'specific zone first');
        $this->assertSame(['barranco', 'miraflores'], $zones[0]->districtCodes);

        $provider = new VendorDeliveryProvider($zones);
        $near = $provider->quote(new ShipmentRequest(1, 'miraflores', Money::ofDecimal('100')));
        $this->assertSame('8.50', $near->cost->toDecimal());
        $this->assertSame('0.00', $provider->quote(new ShipmentRequest(1, 'barranco', Money::ofDecimal('150')))->cost->toDecimal());
        $this->assertSame('15.00', $provider->quote(new ShipmentRequest(1, 'comas', Money::ofDecimal('500')))->cost->toDecimal());

        $restored = array_map(static fn (array $r) => DeliveryZone::fromArray($r), array_map(static fn ($z) => $z->toArray(), $zones));
        $this->assertSame('150.00', $restored[0]->freeFrom->toDecimal());
        $this->assertSame(null, $restored[1]->freeFrom);
    }

    public function testZoneErrors(): void
    {
        $e = $this->assertThrows(ZoneValidationException::class, fn () => DeliveryZone::listFromInput([
            ['name' => 'A', 'districts' => ['lince'], 'cost' => 'gratis', 'eta_days' => '1'],
            ['name' => 'B', 'districts' => ['lince'], 'cost' => '5', 'eta_days' => '40', 'free_from' => '0'],
        ]));
        $this->assertTrue(isset($e->errors['zones.0.cost'], $e->errors['zones.1.eta_days'], $e->errors['zones.1.free_from'], $e->errors['zones.1.districts']));
        $this->assertSame(null, (new VendorDeliveryProvider([]))->quote(new ShipmentRequest(1, 'lince', Money::ofDecimal('10'))));
    }
}
