<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Application;

use Cheppi\Sellers\Domain\Ledger\LedgerEntry;
use Cheppi\Sellers\Domain\Ledger\LedgerEntryType;
use Cheppi\Sellers\Domain\Payout\Payout;
use DateTimeImmutable;
use DomainException;

/**
 * Use cases on an existing payout: the admin records the bank transfer, or cancels it
 * (its money goes back to the vendor's available balance for the next cut).
 */
final class SettlePayout
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly Payouts $payouts
    ) {
    }

    /** @throws DomainException */
    public function pay(int $payoutId, string $reference, DateTimeImmutable $now): Payout
    {
        $payout = $this->payouts->find($payoutId) ?? throw new DomainException('Liquidación no encontrada.');
        return $this->payouts->save($payout->markPaid($reference, $now));
    }

    /** @throws DomainException */
    public function cancel(int $payoutId, DateTimeImmutable $now): Payout
    {
        $payout = $this->payouts->find($payoutId) ?? throw new DomainException('Liquidación no encontrada.');
        $cancelled = $this->payouts->save($payout->cancel());
        // Sales go back to the next cut; the payout entry and this adjustment cancel each other and stay attached.
        $this->ledger->releasePayout($payoutId);
        $this->ledger->add([(new LedgerEntry($payout->vendorId, LedgerEntryType::Adjustment, $payout->amount, $now, $now, null, null, null, 'Liquidación anulada'))->withPayout($payoutId)]);
        return $cancelled;
    }
}
