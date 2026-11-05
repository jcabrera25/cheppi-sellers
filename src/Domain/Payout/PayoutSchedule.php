<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Payout;

use Cheppi\Sellers\Domain\Ledger\Balance;
use Cheppi\Sellers\Domain\Money\Money;
use Cheppi\Sellers\Domain\Rules\BusinessRules;
use DateTimeImmutable;

/**
 * Cut-off dates and payout eligibility, driven entirely by BusinessRules.
 * A configured day beyond a month's length (e.g. 31 in February) means "last day of that month".
 */
final class PayoutSchedule
{
    public function __construct(private readonly BusinessRules $rules)
    {
    }

    /** Next cut-off date on or after $from (date only, time 00:00). */
    public function nextCutoff(DateTimeImmutable $from): DateTimeImmutable
    {
        $start = $from->setTime(0, 0);
        for ($monthOffset = 0; $monthOffset < 2; $monthOffset++) {
            $month = $start->modify('first day of this month')->modify("+{$monthOffset} month");
            $daysInMonth = (int) $month->format('t');
            foreach ($this->rules->payoutDays as $day) {
                $candidate = $month->setDate(
                    (int) $month->format('Y'),
                    (int) $month->format('n'),
                    min($day, $daysInMonth)
                );
                if ($candidate >= $start) {
                    return $candidate;
                }
            }
        }
        // Unreachable with at least one payout day configured.
        return $start;
    }

    /** Moment a sale's money becomes payable: delivery + return window. */
    public function availableAt(DateTimeImmutable $deliveredAt): DateTimeImmutable
    {
        return $deliveredAt->modify(sprintf('+%d days', $this->rules->returnDays));
    }

    /** Amount to pay at a cut-off, or zero when under the configured minimum. */
    public function payableAmount(Balance $balance): Money
    {
        $available = $balance->available;
        if ($available->cents < $this->rules->minimumPayout->cents) {
            return Money::zero($available->currency);
        }
        return $available;
    }
}
