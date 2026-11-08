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

    /** @return list<Fulfillment> newest first */
    public function search(OrderFilter $filter, int $limit, int $offset): array;

    /** @return array<string, int> status => count for the filter without its status */
    public function countSearch(OrderFilter $filter): array;

    /** Every vendor (management panel). @return list<Fulfillment> newest first */
    public function listAll(?FulfillmentStatus $status, int $limit, int $offset): array;

    /** @return array<string, int> status value => count, all vendors */
    public function countsAll(): array;
}
