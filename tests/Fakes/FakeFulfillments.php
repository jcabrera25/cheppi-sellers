<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Tests\Fakes;

use Cheppi\Sellers\Application\Fulfillments;
use Cheppi\Sellers\Domain\Order\Fulfillment;
use Cheppi\Sellers\Domain\Order\FulfillmentStatus;

final class FakeFulfillments implements Fulfillments
{
    /** @var array<string, Fulfillment> */
    public array $items = [];

    public function save(Fulfillment $fulfillment): Fulfillment
    {
        return $this->items[$fulfillment->orderId . ':' . $fulfillment->vendorId] = $fulfillment;
    }

    public function find(int $orderId, int $vendorId): ?Fulfillment
    {
        return $this->items[$orderId . ':' . $vendorId] ?? null;
    }

    public function forOrder(int $orderId): array
    {
        return array_values(array_filter($this->items, static fn (Fulfillment $f): bool => $f->orderId === $orderId));
    }

    public function forVendor(int $vendorId, ?FulfillmentStatus $status, int $limit, int $offset): array
    {
        $list = array_filter($this->items, static fn (Fulfillment $f): bool => $f->vendorId === $vendorId && ($status === null || $f->status === $status));
        return array_slice(array_values($list), $offset, $limit);
    }

    public function countsForVendor(int $vendorId): array
    {
        $counts = [];
        foreach ($this->forVendor($vendorId, null, PHP_INT_MAX, 0) as $f) {
            $counts[$f->status->value] = ($counts[$f->status->value] ?? 0) + 1;
        }
        return $counts;
    }

    public function listAll(?FulfillmentStatus $status, int $limit, int $offset): array
    {
        return array_slice(array_values(array_filter($this->items, static fn (Fulfillment $f): bool => $status === null || $f->status === $status)), $offset, $limit);
    }

    public function countsAll(): array
    {
        $counts = [];
        foreach ($this->items as $f) {
            $counts[$f->status->value] = ($counts[$f->status->value] ?? 0) + 1;
        }
        return $counts;
    }
}
