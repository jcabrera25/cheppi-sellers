<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Application;

use Cheppi\Sellers\Domain\Shipping\DeliveryZone;

/** Port: each vendor's own delivery zones and rates. */
interface DeliveryZones
{
    /** @return list<DeliveryZone> */
    public function forVendor(int $vendorId): array;

    /** @param list<DeliveryZone> $zones */
    public function save(int $vendorId, array $zones): void;
}
