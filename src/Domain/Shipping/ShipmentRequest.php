<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Shipping;

use Cheppi\Sellers\Domain\Money\Money;

/**
 * What a provider needs to price one vendor's part of a cart.
 */
final class ShipmentRequest
{
    public function __construct(
        public readonly int $vendorId,
        public readonly string $districtCode,
        public readonly Money $subtotal,
        public readonly int $weightGrams = 0
    ) {
    }
}
