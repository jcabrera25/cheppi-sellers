<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Application;

use Cheppi\Sellers\Domain\Ledger\Balance;
use Cheppi\Sellers\Domain\Ledger\LedgerEntry;
use DateTimeImmutable;

/** Port: append-only money movements per vendor. */
interface Ledger
{
    /** @param list<LedgerEntry> $entries written together (one transaction) */
    public function add(array $entries): void;

    /** @return list<LedgerEntry> */
    public function forOrder(int $orderId): array;

    /** Releases the held money of one vendor in one order from that moment on. */
    public function release(int $orderId, int $vendorId, DateTimeImmutable $availableAt): void;

    public function balance(int $vendorId, DateTimeImmutable $now): Balance;
}
