<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Shipping;

use Cheppi\Sellers\Domain\Money\Money;

final class ShippingQuote
{
    public function __construct(
        public readonly string $providerCode,
        public readonly string $label,
        public readonly Money $cost,
        public readonly int $etaDays
    ) {
    }
}
