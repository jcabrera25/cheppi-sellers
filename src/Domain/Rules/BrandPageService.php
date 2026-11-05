<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Rules;

use Cheppi\Sellers\Domain\Money\Money;

/**
 * Pricing and terms of the optional, paid "página de marca premium" service.
 */
final class BrandPageService
{
    public const RENEWAL_NONE = 'none';
    public const RENEWAL_MONTHLY = 'monthly';
    public const RENEWAL_YEARLY = 'yearly';
    public const RENEWAL_PERIODS = [self::RENEWAL_NONE, self::RENEWAL_MONTHLY, self::RENEWAL_YEARLY];

    public function __construct(
        public readonly bool $enabled,
        public readonly Money $designFee,
        public readonly Money $renewalFee,
        public readonly string $renewalPeriod,
        public readonly int $revisionRounds,
        public readonly int $deliveryDays
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'design_fee' => $this->designFee->toDecimal(),
            'renewal_fee' => $this->renewalFee->toDecimal(),
            'renewal_period' => $this->renewalPeriod,
            'revision_rounds' => $this->revisionRounds,
            'delivery_days' => $this->deliveryDays,
        ];
    }
}
