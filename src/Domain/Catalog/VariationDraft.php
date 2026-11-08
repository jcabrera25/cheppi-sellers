<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Catalog;

use Cheppi\Sellers\Domain\Money\Money;

/** One variation of a variable product: a combination of attribute options with its own price and stock. */
final class VariationDraft
{
    /** @param array<string, string> $attributes attribute name => option */
    public function __construct(
        public readonly array $attributes,
        public readonly bool $enabled,
        public readonly Money $price,
        public readonly ?Money $salePrice,
        public readonly string $sku,
        public readonly bool $manageStock,
        public readonly ?int $stock,
        public readonly string $stockStatus,
        public readonly ?string $weight,
        public readonly int $imageId,
        public readonly string $description,
        public readonly ?int $id = null
    ) {
    }

    /** "Rojo / 30 ml" */
    public function label(): string
    {
        return implode(' / ', $this->attributes);
    }
}
