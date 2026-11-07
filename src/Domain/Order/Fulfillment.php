<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Order;

use Cheppi\Sellers\Domain\Money\Money;
use DateTimeImmutable;
use DomainException;

/**
 * One vendor's part of a customer order: what they must deliver and how far they are.
 */
final class Fulfillment
{
    public const MAX_TRACKING = 200;

    public function __construct(
        public readonly int $orderId,
        public readonly int $vendorId,
        public readonly FulfillmentStatus $status,
        public readonly int $itemsCount,
        public readonly Money $subtotal,
        public readonly Money $shipping,
        public readonly string $tracking,
        public readonly DateTimeImmutable $createdAt,
        public readonly DateTimeImmutable $updatedAt,
        public readonly ?int $id = null
    ) {
    }

    public function moveTo(FulfillmentStatus $next, string $tracking, DateTimeImmutable $now): self
    {
        $tracking = trim($tracking);
        if (mb_strlen($tracking) > self::MAX_TRACKING) {
            throw new DomainException(sprintf('El seguimiento admite hasta %d caracteres.', self::MAX_TRACKING));
        }
        if ($next === $this->status) {
            return $this->with($this->status, $tracking, $now);
        }
        if (!$this->status->canMoveTo($next)) {
            throw new DomainException(sprintf('No se puede pasar de «%s» a «%s».', $this->status->label(), $next->label()));
        }
        return $this->with($next, $tracking, $now);
    }

    public function cancel(DateTimeImmutable $now): self
    {
        return $this->with(FulfillmentStatus::Cancelled, $this->tracking, $now);
    }

    private function with(FulfillmentStatus $status, string $tracking, DateTimeImmutable $now): self
    {
        return new self($this->orderId, $this->vendorId, $status, $this->itemsCount, $this->subtotal, $this->shipping, $tracking, $this->createdAt, $now, $this->id);
    }
}
