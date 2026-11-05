<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Shipping;

use Cheppi\Sellers\Domain\Money\Money;

/**
 * The vendor delivers with its own service, using the zones it configured.
 * Shipping money goes to the vendor's balance.
 */
final class VendorDeliveryProvider implements ShippingProvider
{
    /** @param list<DeliveryZone> $zones */
    public function __construct(private readonly array $zones)
    {
    }

    public function code(): string
    {
        return 'vendor_delivery';
    }

    public function label(): string
    {
        return 'Delivery de la marca';
    }

    public function payee(): string
    {
        return 'vendor';
    }

    public function quote(ShipmentRequest $request): ?ShippingQuote
    {
        foreach ($this->zones as $zone) {
            if (!$zone->covers($request->districtCode)) {
                continue;
            }
            $cost = $zone->cost;
            if ($zone->freeFrom !== null && $request->subtotal->cents >= $zone->freeFrom->cents) {
                $cost = Money::zero($cost->currency);
            }
            return new ShippingQuote($this->code(), $zone->name, $cost, $zone->etaDays);
        }
        return null;
    }
}
