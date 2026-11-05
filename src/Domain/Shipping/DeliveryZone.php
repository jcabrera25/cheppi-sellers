<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Shipping;

use Cheppi\Sellers\Domain\Money\Money;

/**
 * A vendor-defined zone: a set of districts, a price, an ETA and an optional free-shipping threshold.
 */
final class DeliveryZone
{
    /** @param list<string> $districtCodes */
    public function __construct(
        public readonly string $name,
        public readonly array $districtCodes,
        public readonly Money $cost,
        public readonly int $etaDays,
        public readonly ?Money $freeFrom = null
    ) {
    }

    public function covers(string $districtCode): bool
    {
        return in_array($districtCode, $this->districtCodes, true);
    }
}
