<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Tests\Fakes;

use Cheppi\Sellers\Application\Payouts;
use Cheppi\Sellers\Domain\Payout\Payout;
use Cheppi\Sellers\Domain\Payout\PayoutStatus;
use DateTimeImmutable;

final class FakePayouts implements Payouts
{
    /** @var array<int, Payout> */
    public array $items = [];

    public function save(Payout $payout): Payout
    {
        $payout = $payout->id === null ? $payout->withId(count($this->items) + 1) : $payout;
        return $this->items[(int) $payout->id] = $payout;
    }

    public function find(int $id): ?Payout
    {
        return $this->items[$id] ?? null;
    }

    public function existsFor(int $vendorId, DateTimeImmutable $cutoff): bool
    {
        foreach ($this->items as $p) {
            if ($p->vendorId === $vendorId && $p->cutoffDate->format('Y-m-d') === $cutoff->format('Y-m-d') && $p->status !== PayoutStatus::Cancelled) {
                return true;
            }
        }
        return false;
    }

    public function list(?PayoutStatus $status, ?int $vendorId, int $limit, int $offset): array
    {
        return array_slice(array_values(array_filter($this->items, static fn (Payout $p): bool => ($status === null || $p->status === $status) && ($vendorId === null || $p->vendorId === $vendorId))), $offset, $limit);
    }

    public function totals(): array
    {
        $t = [];
        foreach ($this->items as $p) {
            $t[$p->status->value]['count'] = ($t[$p->status->value]['count'] ?? 0) + 1;
            $t[$p->status->value]['cents'] = ($t[$p->status->value]['cents'] ?? 0) + $p->amount->cents;
        }
        return $t;
    }
}
