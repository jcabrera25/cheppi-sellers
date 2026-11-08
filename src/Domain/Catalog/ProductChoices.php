<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Catalog;

/** What a product may point to: categories, brands and the vendor's own products (for up-sells/cross-sells). */
final class ProductChoices
{
    /**
     * @param array<int, string> $categories id => name
     * @param array<int, string> $brands     id => name
     * @param array<int, string> $linkable   product id => name (same owner, never the product itself)
     */
    public function __construct(
        public readonly array $categories,
        public readonly array $brands = [],
        public readonly array $linkable = []
    ) {
    }
}
