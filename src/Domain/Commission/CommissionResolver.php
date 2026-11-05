<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Commission;

use Cheppi\Sellers\Domain\Money\Money;
use Cheppi\Sellers\Domain\Rules\BusinessRules;

/**
 * Decides which commission applies to a sale.
 *
 * Precedence: vendor override > lowest matching category rate > vendor's plan > marketplace default.
 * "Lowest category" favours the seller when a product sits in several categories.
 */
final class CommissionResolver
{
    public function __construct(private readonly BusinessRules $rules)
    {
    }

    /**
     * @param list<int> $categoryIds product category term ids
     */
    public function resolve(?int $vendorOverrideBps, string $planId, array $categoryIds): CommissionSnapshot
    {
        if ($vendorOverrideBps !== null) {
            return new CommissionSnapshot($vendorOverrideBps, CommissionSnapshot::SOURCE_VENDOR);
        }

        $categoryRates = [];
        foreach ($categoryIds as $id) {
            if (isset($this->rules->categoryCommissionsBps[$id])) {
                $categoryRates[] = $this->rules->categoryCommissionsBps[$id];
            }
        }
        if ($categoryRates !== []) {
            return new CommissionSnapshot(min($categoryRates), CommissionSnapshot::SOURCE_CATEGORY);
        }

        $plan = $this->rules->plan($planId);
        if ($plan !== null) {
            return new CommissionSnapshot($plan->commissionBps, CommissionSnapshot::SOURCE_PLAN);
        }

        return new CommissionSnapshot($this->rules->defaultCommissionBps, CommissionSnapshot::SOURCE_DEFAULT);
    }

    public static function commissionFor(Money $lineTotal, CommissionSnapshot $snapshot): Money
    {
        return $lineTotal->percentage($snapshot->bps);
    }
}
