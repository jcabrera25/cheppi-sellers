<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Application;

use Cheppi\Sellers\Domain\Order\SaleAllocator;
use Cheppi\Sellers\Domain\Order\SaleLine;
use Cheppi\Sellers\Domain\Order\ShippingCharge;
use DateTimeImmutable;

/**
 * Use case: an order was paid. Writes sales, commissions and shipping to the ledger and opens one
 * fulfillment per vendor. Idempotent: a second call for the same order does nothing.
 */
final class RecordOrderSales
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly Fulfillments $fulfillments
    ) {
    }

    /**
     * @param list<SaleLine>       $lines    only lines of products that belong to vendors
     * @param list<ShippingCharge> $shipping
     * @return bool true when something was recorded
     */
    public function __invoke(int $orderId, array $lines, array $shipping, DateTimeImmutable $paidAt): bool
    {
        if ($lines === [] || $this->ledger->forOrder($orderId) !== []) {
            return false;
        }
        $result = SaleAllocator::allocate($orderId, $lines, $shipping, $paidAt);
        $this->ledger->add($result['entries']);
        foreach ($result['fulfillments'] as $fulfillment) {
            $this->fulfillments->save($fulfillment);
        }
        return true;
    }
}
