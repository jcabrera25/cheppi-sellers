<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Application;

use Cheppi\Sellers\Domain\Ledger\LedgerEntryType;
use DateTimeImmutable;

/**
 * Use case: a recorded order was cancelled or fully refunded. Writes the opposite entries (the ledger is
 * never edited) and cancels the fulfillments. Idempotent.
 */
final class ReverseOrderSales
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly Fulfillments $fulfillments
    ) {
    }

    public function __invoke(int $orderId, string $reason, DateTimeImmutable $now): bool
    {
        $entries = $this->ledger->forOrder($orderId);
        if ($entries === [] || array_filter($entries, static fn ($e): bool => $e->type === LedgerEntryType::Refund) !== []) {
            return false;
        }
        $this->ledger->add(array_map(static fn ($e) => $e->reversal($now, $reason), $entries));
        foreach ($this->fulfillments->forOrder($orderId) as $fulfillment) {
            $this->fulfillments->save($fulfillment->cancel($now));
        }
        return true;
    }
}
