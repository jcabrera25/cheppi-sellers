<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Application;

use Cheppi\Sellers\Domain\Order\Fulfillment;
use Cheppi\Sellers\Domain\Order\FulfillmentStatus;
use Cheppi\Sellers\Domain\Payout\PayoutSchedule;
use DateTimeImmutable;
use DomainException;

/**
 * Use case: the vendor moves their part of an order forward (preparing, shipped, delivered) and adds tracking.
 * On delivery their money starts the return window and becomes payable after it.
 */
final class UpdateFulfillment
{
    public function __construct(
        private readonly Fulfillments $fulfillments,
        private readonly Ledger $ledger,
        private readonly BusinessRulesRepository $rules
    ) {
    }

    /** @throws DomainException */
    public function __invoke(int $vendorId, int $orderId, FulfillmentStatus $next, string $tracking, DateTimeImmutable $now): Fulfillment
    {
        $current = $this->fulfillments->find($orderId, $vendorId) ?? throw new DomainException('Pedido no encontrado.');
        $updated = $current->moveTo($next, $tracking, $now);
        $saved = $this->fulfillments->save($updated);
        if ($next === FulfillmentStatus::Delivered && $current->status !== FulfillmentStatus::Delivered) {
            $this->ledger->release($orderId, $vendorId, (new PayoutSchedule($this->rules->current()))->availableAt($now));
        }
        return $saved;
    }
}
