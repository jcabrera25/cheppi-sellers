<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Catalog;

use Cheppi\Sellers\Domain\Money\Money;

/** One option of a variable product (a shade, a size). */
final class VariationDraft
{
    public function __construct(
        public readonly string $option,
        public readonly Money $price,
        public readonly ?Money $salePrice,
        public readonly string $sku,
        public readonly int $stock,
        public readonly ?int $id = null
    ) {
    }
}
