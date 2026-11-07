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

    /** available_at of sale money while the order is not delivered yet. */
    public const UNTIL_DELIVERED = '9999-12-31 00:00:00';

    public static function untilDelivered(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::UNTIL_DELIVERED, new \DateTimeZone('UTC'));
    }

    /** The opposite entry, used when an order is cancelled or refunded. */
    public function reversal(DateTimeImmutable $at, string $note): self
    {
        $type = $this->type === LedgerEntryType::Commission ? LedgerEntryType::Commission : LedgerEntryType::Refund;
        return new self($this->vendorId, $type, $this->amount->negate(), $at, $this->availableAt, $this->orderId, $this->orderItemId, $this->commissionBps, $note);
    }

    public function isAvailable(DateTimeImmutable $now): bool
    {
        return $this->availableAt <= $now;
    }
}
