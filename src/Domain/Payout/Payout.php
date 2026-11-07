<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Payout;

use Cheppi\Sellers\Domain\Money\Money;
use DateTimeImmutable;
use DomainException;

/** Money owed to a vendor at a cut-off, and whether Cheppi already transferred it. */
final class Payout
{
    public function __construct(
        public readonly int $vendorId,
        public readonly Money $amount,
        public readonly DateTimeImmutable $cutoffDate,
        public readonly PayoutStatus $status,
        public readonly string $reference,
        public readonly DateTimeImmutable $createdAt,
        public readonly ?DateTimeImmutable $paidAt = null,
        public readonly ?int $id = null
    ) {
    }

    public function markPaid(string $reference, DateTimeImmutable $now): self
    {
        if ($this->status !== PayoutStatus::Pending) {
            throw new DomainException('Solo se puede pagar una liquidación pendiente.');
        }
        $reference = trim($reference);
        if ($reference === '' || mb_strlen($reference) > 120) {
            throw new DomainException('Escribe el número de operación de la transferencia.');
        }
        return new self($this->vendorId, $this->amount, $this->cutoffDate, PayoutStatus::Paid, $reference, $this->createdAt, $now, $this->id);
    }

    public function cancel(): self
    {
        if ($this->status !== PayoutStatus::Pending) {
            throw new DomainException('Solo se puede anular una liquidación pendiente.');
        }
        return new self($this->vendorId, $this->amount, $this->cutoffDate, PayoutStatus::Cancelled, $this->reference, $this->createdAt, null, $this->id);
    }

    public function withId(int $id): self
    {
        return new self($this->vendorId, $this->amount, $this->cutoffDate, $this->status, $this->reference, $this->createdAt, $this->paidAt, $id);
    }
}
