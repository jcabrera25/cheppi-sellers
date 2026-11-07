<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Application;

use Cheppi\Sellers\Domain\Ledger\LedgerEntry;
use Cheppi\Sellers\Domain\Ledger\LedgerEntryType;
use Cheppi\Sellers\Domain\Money\Money;
use Cheppi\Sellers\Domain\Payout\Payout;
use Cheppi\Sellers\Domain\Payout\PayoutStatus;
use DateTimeImmutable;

/**
 * Use case: the cut-off. For every vendor, the money that is payable at the cut-off date (and not in an
 * earlier payout) becomes one pending payout, if it reaches the minimum. A "payout" entry takes it out of
 * the available balance. Running it twice for the same date does nothing new.
 */
final class GeneratePayouts
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly Payouts $payouts,
        private readonly BusinessRulesRepository $rules
    ) {
    }

    /**
     * @param DateTimeImmutable $cutoff money released up to this moment is paid; the payout is dated on its day
     * @return list<Payout> created payouts
     */
    public function __invoke(DateTimeImmutable $cutoff, DateTimeImmutable $now): array
    {
        $day = $cutoff->setTime(0, 0);
        $minimum = $this->rules->current()->minimumPayout;
        $created = [];
        foreach ($this->ledger->vendorsWithPayable($cutoff) as $vendorId) {
            if ($this->payouts->existsFor($vendorId, $day)) {
                continue;
            }
            $entries = $this->ledger->payable($vendorId, $cutoff);
            $cents = array_sum(array_map(static fn (LedgerEntry $e): int => $e->amount->cents, $entries));
            if ($cents <= 0 || $cents < $minimum->cents) {
                continue;
            }
            $amount = Money::ofCents($cents, $minimum->currency);
            $payout = $this->payouts->save(new Payout($vendorId, $amount, $day, PayoutStatus::Pending, '', $now));
            $this->ledger->add([(new LedgerEntry($vendorId, LedgerEntryType::Payout, $amount->negate(), $now, $now, null, null, null, sprintf('Liquidación del %s', $day->format('d/m/Y'))))->withPayout($payout->id)]);
            $this->ledger->assignPayout(array_map(static fn (LedgerEntry $e): int => (int) $e->id, $entries), (int) $payout->id);
            $created[] = $payout;
        }
        return $created;
    }
}
