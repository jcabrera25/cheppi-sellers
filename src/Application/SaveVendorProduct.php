<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Application;

use Cheppi\Sellers\Domain\Catalog\ProductDraft;
use Cheppi\Sellers\Domain\Catalog\ProductStatus;
use Cheppi\Sellers\Domain\Catalog\ProductValidationException;
use Cheppi\Sellers\Domain\Vendor\VendorStatus;

/**
 * Use case: an approved vendor creates or edits a product from the panel.
 * Applies the plan's product limit, image limits and the moderation rule.
 */
final class SaveVendorProduct
{
    public const MAX_IMAGES = 8;

    public function __construct(
        private readonly VendorRepository $vendors,
        private readonly ProductCatalog $catalog,
        private readonly BusinessRulesRepository $rules
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @param bool $byAdmin a Cheppi admin saving for the vendor: publishes without moderation
     * @param list<UploadedFile> $newImages
     * @param list<int> $removeImageIds
     * @return array{id: int, status: ProductStatus}
     * @throws ProductValidationException
     */
    public function __invoke(int $userId, ?int $productId, array $input, array $newImages, array $removeImageIds, bool $saveAsDraft, bool $byAdmin = false): array
    {
        $vendor = $this->vendors->findByUserId($userId);
        if ($vendor === null || $vendor->status !== VendorStatus::Approved) {
            throw new ProductValidationException(['form' => 'Tu tienda debe estar aprobada para publicar productos.']);
        }
        $rules = $this->rules->current();

        $current = null;
        if ($productId !== null) {
            $current = $this->catalog->statusOf($userId, $productId);
            if ($current === null) {
                throw new ProductValidationException(['form' => 'Producto no encontrado.']);
            }
        } else {
            $limit = $rules->plan($vendor->planId)?->productLimit;
            if ($limit !== null && $this->catalog->countForVendor($userId) >= $limit) {
                throw new ProductValidationException(['form' => sprintf(
                    'Tu plan permite %d productos. Elimina alguno o cambia de plan para agregar más.',
                    $limit
                )]);
            }
        }

        $errors = [];
        foreach ($newImages as $i => $image) {
            try {
                $image->assertImage('images');
            } catch (ProductValidationException $e) {
                $errors += $e->errors;
            }
        }
        $kept = $productId !== null ? max(0, $this->catalog->imageCount($userId, $productId) - count($removeImageIds)) : 0;
        $total = $kept + count($newImages);
        if ($total > self::MAX_IMAGES) {
            $errors['images'] = sprintf('Máximo %d fotos por producto.', self::MAX_IMAGES);
        } elseif ($total === 0 && !$saveAsDraft) {
            $errors['images'] = 'Sube al menos una foto para publicar.';
        }

        try {
            $draft = ProductDraft::fromArray($input, $this->catalog->choices($userId, $productId));
        } catch (ProductValidationException $e) {
            throw new ProductValidationException($errors + $e->errors);
        }
        if ($errors !== []) {
            throw new ProductValidationException($errors);
        }

        // Cheppi's own team publishes directly; vendors go through moderation when it is on.
        $status = $byAdmin && !$saveAsDraft ? ProductStatus::Published : ProductStatus::afterSave($current, $saveAsDraft, $rules);
        $id = $this->catalog->save($userId, $productId, $draft, $status, $newImages, $removeImageIds);
        return ['id' => $id, 'status' => $status];
    }
}
