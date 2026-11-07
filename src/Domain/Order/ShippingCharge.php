<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Order;

use Cheppi\Sellers\Domain\Money\Money;

/** Shipping the customer paid for a vendor's package, when that vendor delivers it. */
final class ShippingCharge
{
    public function __construct(
        public readonly int $orderItemId,
        public readonly int $vendorId,
        public readonly Money $amount
    ) {
    }
}
