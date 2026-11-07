<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Order;

use Cheppi\Sellers\Domain\Money\Money;

/** A product line of a paid order, already matched to its vendor and commission. */
final class SaleLine
{
    public function __construct(
        public readonly int $orderItemId,
        public readonly int $vendorId,
        public readonly int $quantity,
        public readonly Money $total,
        public readonly int $commissionBps
    ) {
    }
}
