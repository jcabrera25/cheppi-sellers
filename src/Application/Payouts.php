<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Application;

use Cheppi\Sellers\Domain\Payout\Payout;
use Cheppi\Sellers\Domain\Payout\PayoutStatus;
use DateTimeImmutable;

/** Port: payouts to vendors. */
interface Payouts
{
    public function save(Payout $payout): Payout;

    public function find(int $id): ?Payout;

    public function existsFor(int $vendorId, DateTimeImmutable $cutoff): bool;

    /** @return list<Payout> newest first */
    public function list(?PayoutStatus $status, ?int $vendorId, int $limit, int $offset): array;

    /** @return array<string, array{count: int, cents: int}> by status */
    public function totals(): array;
}
