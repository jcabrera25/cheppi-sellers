<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Commission;

/**
 * The commission rate frozen on an order line, and which rule produced it.
 * Freezing it means later rule changes never alter past sales.
 */
final class CommissionSnapshot
{
    public const SOURCE_VENDOR = 'vendor';
    public const SOURCE_CATEGORY = 'category';
    public const SOURCE_PLAN = 'plan';
    public const SOURCE_DEFAULT = 'default';

    public function __construct(
        public readonly int $bps,
        public readonly string $source
    ) {
    }
}
