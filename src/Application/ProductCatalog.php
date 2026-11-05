<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Application;

use Cheppi\Sellers\Domain\Catalog\ProductDraft;
use Cheppi\Sellers\Domain\Catalog\ProductStatus;

/**
 * Where vendor products live (WooCommerce today). Every call is scoped to the owner (vendor user id).
 */
interface ProductCatalog
{
    /** @return array<int, string> term id => name */
    public function categories(): array;

    /** Products that count against the plan limit (everything except trash). */
    public function countForVendor(int $ownerId): int;

    /**
     * Creates or updates a product and its images.
     *
     * @param list<UploadedFile> $newImages appended after kept images
     * @param list<int> $removeImageIds attachment ids to detach
     * @return int product id
     */
    public function save(int $ownerId, ?int $productId, ProductDraft $draft, ProductStatus $status, array $newImages, array $removeImageIds): int;

    public function statusOf(int $ownerId, int $productId): ?ProductStatus;

    /** Current number of images (gallery + main), for the per-product limit. */
    public function imageCount(int $ownerId, int $productId): int;

    public function trash(int $ownerId, int $productId): bool;
}
