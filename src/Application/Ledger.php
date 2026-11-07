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

    /** @return list<int> vendors that have payable money not yet in a payout */
    public function vendorsWithPayable(DateTimeImmutable $cutoff): array;

    /** @return list<LedgerEntry> entries payable at $cutoff and not yet in a payout */
    public function payable(int $vendorId, DateTimeImmutable $cutoff): array;

    /** @param list<int> $entryIds */
    public function assignPayout(array $entryIds, int $payoutId): void;

    /** Entries of a cancelled payout go back to the next cut; its own "payout" entry stays with it. */
    public function releasePayout(int $payoutId): void;

    /** @return list<LedgerEntry> */
    public function forPayout(int $payoutId): array;

    /** @return list<LedgerEntry> newest first */
    public function forVendor(int $vendorId, int $limit, int $offset = 0): array;
}
