<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Tests\Fakes;

use Cheppi\Sellers\Application\ProductCatalog;
use Cheppi\Sellers\Domain\Catalog\ProductDraft;
use Cheppi\Sellers\Domain\Catalog\ProductStatus;

final class FakeCatalog implements ProductCatalog
{
    /** @var array<int, array{owner: int, draft: ProductDraft, status: ProductStatus, images: int}> */
    public array $products = [];
    private int $next = 100;

    public function categories(): array
    {
        return [15 => 'Maquillaje', 16 => 'Fragancias'];
    }

    public function countForVendor(int $ownerId): int
    {
        return count(array_filter($this->products, static fn (array $p): bool => $p['owner'] === $ownerId));
    }

    public function save(int $ownerId, ?int $productId, ProductDraft $draft, ProductStatus $status, array $newImages, array $removeImageIds): int
    {
        $id = $productId ?? $this->next++;
        $images = ($this->products[$id]['images'] ?? 0) - count($removeImageIds) + count($newImages);
        $this->products[$id] = ['owner' => $ownerId, 'draft' => $draft, 'status' => $status, 'images' => $images];
        return $id;
    }

    public function statusOf(int $ownerId, int $productId): ?ProductStatus
    {
        $p = $this->products[$productId] ?? null;
        return $p !== null && $p['owner'] === $ownerId ? $p['status'] : null;
    }

    public function imageCount(int $ownerId, int $productId): int
    {
        return $this->products[$productId]['images'] ?? 0;
    }

    public function trash(int $ownerId, int $productId): bool
    {
        if (($this->products[$productId]['owner'] ?? null) !== $ownerId) {
            return false;
        }
        unset($this->products[$productId]);
        return true;
    }
}
