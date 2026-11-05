<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Ledger;

use Cheppi\Sellers\Domain\Money\Money;
use DateTimeImmutable;

/**
 * A vendor's balance split into what can be paid out now and what is still on hold
 * (inside the delivery + return window).
 */
final class Balance
{
    public function __construct(
        public readonly Money $available,
        public readonly Money $pending
    ) {
    }

    public function total(): Money
    {
        return $this->available->add($this->pending);
    }

    /** @param iterable<LedgerEntry> $entries */
    public static function fromEntries(iterable $entries, DateTimeImmutable $now, string $currency = 'PEN'): self
    {
        $available = Money::zero($currency);
        $pending = Money::zero($currency);
        foreach ($entries as $entry) {
            // Debits (payouts, refunds, fees) reduce what is available immediately.
            if ($entry->isAvailable($now) || $entry->amount->isNegative()) {
                $available = $available->add($entry->amount);
            } else {
                $pending = $pending->add($entry->amount);
            }
        }
        return new self($available, $pending);
    }
}
