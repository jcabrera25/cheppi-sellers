<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Tests\Unit\Domain;

use Cheppi\Sellers\Domain\Ledger\Balance;
use Cheppi\Sellers\Domain\Ledger\LedgerEntry;
use Cheppi\Sellers\Domain\Ledger\LedgerEntryType;
use Cheppi\Sellers\Domain\Money\Money;
use Cheppi\Sellers\Domain\Payout\PayoutSchedule;
use Cheppi\Sellers\Domain\Rules\BusinessRules;
use Cheppi\Sellers\Tests\TestCase;
use DateTimeImmutable;

final class LedgerAndPayoutTest extends TestCase
{
    private function entry(LedgerEntryType $type, string $amount, string $availableAt): LedgerEntry
    {
        $created = new DateTimeImmutable('2026-11-01');
        return new LedgerEntry(7, $type, Money::ofDecimal($amount), $created, new DateTimeImmutable($availableAt));
    }

    public function testBalanceSplitsAvailableAndPending(): void
    {
        $entries = [
            $this->entry(LedgerEntryType::Sale, '100', '2026-11-03'),
            $this->entry(LedgerEntryType::Commission, '-8', '2026-11-03'),
            $this->entry(LedgerEntryType::Sale, '50', '2026-11-20'),
            $this->entry(LedgerEntryType::Commission, '-4', '2026-11-20'),
        ];
        $balance = Balance::fromEntries($entries, new DateTimeImmutable('2026-11-10'));
        // A commission waits with its sale; payouts and fees count immediately.
        $this->assertSame('92.00', $balance->available->toDecimal());
        $this->assertSame('46.00', $balance->pending->toDecimal());
        $this->assertSame('138.00', $balance->total()->toDecimal());
    }

    public function testNextCutoffUsesLastDayOfShortMonths(): void
    {
        $schedule = new PayoutSchedule(BusinessRules::defaults()); // days 15 and 31
        $this->assertSame('2026-02-15', $schedule->nextCutoff(new DateTimeImmutable('2026-02-03'))->format('Y-m-d'));
        $this->assertSame('2026-02-28', $schedule->nextCutoff(new DateTimeImmutable('2026-02-16'))->format('Y-m-d'));
        $this->assertSame('2026-12-15', $schedule->nextCutoff(new DateTimeImmutable('2026-12-01 18:30'))->format('Y-m-d'));
        $this->assertSame('2027-01-15', $schedule->nextCutoff(new DateTimeImmutable('2027-01-01'))->format('Y-m-d'));
    }

    public function testCutoffOnTheSameDayCounts(): void
    {
        $schedule = new PayoutSchedule(BusinessRules::defaults());
        $this->assertSame('2026-11-15', $schedule->nextCutoff(new DateTimeImmutable('2026-11-15 23:00'))->format('Y-m-d'));
    }

    public function testAvailableAfterReturnWindow(): void
    {
        $schedule = new PayoutSchedule(BusinessRules::defaults()); // 7 return days
        $this->assertSame('2026-11-12', $schedule->availableAt(new DateTimeImmutable('2026-11-05'))->format('Y-m-d'));
    }

    public function testMinimumPayout(): void
    {
        $schedule = new PayoutSchedule(BusinessRules::defaults()); // minimum S/ 20
        $small = new Balance(Money::ofDecimal('19.99'), Money::zero());
        $ok = new Balance(Money::ofDecimal('20'), Money::zero());
        $this->assertSame(0, $schedule->payableAmount($small)->cents);
        $this->assertSame(2000, $schedule->payableAmount($ok)->cents);
    }
}
