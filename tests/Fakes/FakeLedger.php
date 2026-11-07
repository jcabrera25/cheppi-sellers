<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Tests\Fakes;

use Cheppi\Sellers\Application\Ledger;
use Cheppi\Sellers\Domain\Ledger\Balance;
use Cheppi\Sellers\Domain\Ledger\LedgerEntry;
use DateTimeImmutable;

final class FakeLedger implements Ledger
{
    /** @var list<LedgerEntry> */
    public array $entries = [];

    public function add(array $entries): void
    {
        array_push($this->entries, ...$entries);
    }

    public function forOrder(int $orderId): array
    {
        return array_values(array_filter($this->entries, static fn (LedgerEntry $e): bool => $e->orderId === $orderId));
    }

    public function release(int $orderId, int $vendorId, DateTimeImmutable $availableAt): void
    {
        foreach ($this->entries as $i => $e) {
            if ($e->orderId === $orderId && $e->vendorId === $vendorId) {
                $this->entries[$i] = new LedgerEntry($e->vendorId, $e->type, $e->amount, $e->createdAt, $availableAt, $e->orderId, $e->orderItemId, $e->commissionBps, $e->note);
            }
        }
    }

    public function balance(int $vendorId, DateTimeImmutable $now): Balance
    {
        return Balance::fromEntries(array_filter($this->entries, static fn (LedgerEntry $e): bool => $e->vendorId === $vendorId), $now);
    }
}
