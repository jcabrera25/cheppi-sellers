<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Application;

use Cheppi\Sellers\Domain\Order\Fulfillment;
use Cheppi\Sellers\Domain\Order\FulfillmentStatus;

/** Port: each vendor's part of each order. */
interface Fulfillments
{
    public function save(Fulfillment $fulfillment): Fulfillment;

    public function find(int $orderId, int $vendorId): ?Fulfillment;

    /** @return list<Fulfillment> */
    public function forOrder(int $orderId): array;

    /** @return list<Fulfillment> newest first */
    public function forVendor(int $vendorId, ?FulfillmentStatus $status, int $limit, int $offset): array;

    /** @return array<string, int> status value => count */
    public function countsForVendor(int $vendorId): array;
}
