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

    private int $nextId = 1;

    public function add(array $entries): void
    {
        foreach ($entries as $e) {
            $this->entries[] = new LedgerEntry($e->vendorId, $e->type, $e->amount, $e->createdAt, $e->availableAt, $e->orderId, $e->orderItemId, $e->commissionBps, $e->note, $this->nextId++, $e->payoutId);
        }
    }

    public function vendorsWithPayable(DateTimeImmutable $cutoff): array
    {
        $ids = [];
        foreach ($this->entries as $e) {
            if ($e->isPayableAt($cutoff)) {
                $ids[$e->vendorId] = $e->vendorId;
            }
        }
        return array_values($ids);
    }

    public function payable(int $vendorId, DateTimeImmutable $cutoff): array
    {
        return array_values(array_filter($this->entries, static fn (LedgerEntry $e): bool => $e->vendorId === $vendorId && $e->isPayableAt($cutoff)));
    }

    public function assignPayout(array $entryIds, int $payoutId): void
    {
        foreach ($this->entries as $i => $e) {
            if (in_array($e->id, $entryIds, true)) {
                $this->entries[$i] = $e->withPayout($payoutId);
            }
        }
    }

    public function releasePayout(int $payoutId): void
    {
        foreach ($this->entries as $i => $e) {
            if ($e->payoutId === $payoutId && $e->type !== \Cheppi\Sellers\Domain\Ledger\LedgerEntryType::Payout) {
                $this->entries[$i] = $e->withPayout(null);
            }
        }
    }

    public function forPayout(int $payoutId): array
    {
        return array_values(array_filter($this->entries, static fn (LedgerEntry $e): bool => $e->payoutId === $payoutId));
    }

    public function forVendor(int $vendorId, int $limit, int $offset = 0): array
    {
        return array_slice(array_reverse(array_values(array_filter($this->entries, static fn (LedgerEntry $e): bool => $e->vendorId === $vendorId))), $offset, $limit);
    }

    public function forOrder(int $orderId): array
    {
        return array_values(array_filter($this->entries, static fn (LedgerEntry $e): bool => $e->orderId === $orderId));
    }

    public function release(int $orderId, int $vendorId, DateTimeImmutable $availableAt): void
    {
        foreach ($this->entries as $i => $e) {
            if ($e->orderId === $orderId && $e->vendorId === $vendorId) {
                $this->entries[$i] = new LedgerEntry($e->vendorId, $e->type, $e->amount, $e->createdAt, $availableAt, $e->orderId, $e->orderItemId, $e->commissionBps, $e->note, $e->id, $e->payoutId);
            }
        }
    }

    public function balance(int $vendorId, DateTimeImmutable $now): Balance
    {
        return Balance::fromEntries(array_filter($this->entries, static fn (LedgerEntry $e): bool => $e->vendorId === $vendorId), $now);
    }
}
