<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Rules;

use Cheppi\Sellers\Domain\Money\Money;

/**
 * A seller plan (e.g. "Emprende", "Pro"). Values are editable from the Business Rules screen.
 */
final class Plan
{
    /**
     * @param int|null $productLimit null = unlimited
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly Money $monthlyFee,
        public readonly ?int $productLimit,
        public readonly int $commissionBps
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'monthly_fee' => $this->monthlyFee->toDecimal(),
            'product_limit' => $this->productLimit,
            'commission_bps' => $this->commissionBps,
        ];
    }
}
