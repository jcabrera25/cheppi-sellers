<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Promotion;

use Cheppi\Sellers\Domain\Money\Money;
use DateTimeImmutable;

/** A paid featured product: N days at the price per day set in the business rules. */
final class FeaturedSpot
{
    public const MAX_DAYS = 30;

    public function __construct(
        public readonly int $productId,
        public readonly int $days,
        public readonly Money $price,
        public readonly DateTimeImmutable $until
    ) {
    }

    /** @throws PromotionValidationException */
    public static function quote(int $productId, mixed $days, Money $perDay, DateTimeImmutable $now, ?DateTimeImmutable $currentUntil = null): self
    {
        $d = filter_var($days, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => self::MAX_DAYS]]);
        if ($productId <= 0 || $d === false) {
            throw new PromotionValidationException(['days' => sprintf('Elige un producto y de 1 a %d días.', self::MAX_DAYS)]);
        }
        // Buying again extends the current period instead of overlapping it.
        $from = $currentUntil !== null && $currentUntil > $now ? $currentUntil : $now;
        return new self($productId, $d, Money::ofCents($perDay->cents * $d, $perDay->currency), $from->modify("+{$d} days"));
    }
}
