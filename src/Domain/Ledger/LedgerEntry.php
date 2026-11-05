<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Ledger;

use Cheppi\Sellers\Domain\Money\Money;
use DateTimeImmutable;

/**
 * One immutable movement in a vendor's ledger. Corrections are new entries, never edits.
 */
final class LedgerEntry
{
    public function __construct(
        public readonly int $vendorId,
        public readonly LedgerEntryType $type,
        public readonly Money $amount,
        public readonly DateTimeImmutable $createdAt,
        public readonly DateTimeImmutable $availableAt,
        public readonly ?int $orderId = null,
        public readonly ?int $orderItemId = null,
        public readonly ?int $commissionBps = null,
        public readonly string $note = '',
        public readonly ?int $id = null
    ) {
    }

    public function isAvailable(DateTimeImmutable $now): bool
    {
        return $this->availableAt <= $now;
    }
}
