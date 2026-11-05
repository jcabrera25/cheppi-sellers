<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Catalog;

use Cheppi\Sellers\Application\ProductCatalog;
use Cheppi\Sellers\Domain\Catalog\ProductDraft;
use Cheppi\Sellers\Domain\Catalog\ProductStatus;
use Cheppi\Sellers\Domain\Catalog\ProductValidationException;
use Cheppi\Sellers\Domain\Money\Money;
use WC_Data_Exception;
use WC_Product;
use WC_Product_Attribute;
use WC_Product_Variable;
use WC_Product_Variation;
use WP_Query;

/**
 * Vendor products stored as regular WooCommerce products (post_author = vendor user),
 * so the theme, cart, checkout and search work with them unchanged.
 */
final class WcProductCatalog implements ProductCatalog
{
    private const VENDOR_STATUSES = ['draft', 'pending', 'publish', 'private'];

    /** @var array<int, string>|null */
    private ?array $categories = null;

    public function __construct(private readonly ImageUploader $images)
    {
    }

    public function categories(): array
    {
        if ($this->categories === null) {
            $terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'fields' => 'id=>name', 'orderby' => 'name']);
            $this->categories = is_array($terms) ? array_map('strval', $terms) : [];
            unset($this->categories[(int) get_option('default_product_cat')]); // "Sin categorizar"
        }
        return $this->categories;
    }

    public function countForVendor(int $ownerId): int
    {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_author = %d AND post_status IN ('draft','pending','publish','private')",
            $ownerId
        ));
    }

    public function statusOf(int $ownerId, int $productId): ?ProductStatus
    {
        $post = get_post($productId);
        if (!$post || $post->post_type !== 'product' || (int) $post->post_author !== $ownerId || !in_array($post->post_status, self::VENDOR_STATUSES, true)) {
            return null;
        }
        return ProductStatus::tryFrom($post->post_status) ?? ProductStatus::Published;
    }

    public function imageCount(int $ownerId, int $productId): int
    {
        $product = $this->owned($ownerId, $productId);
        return $product ? count($this->imageIds($product)) : 0;
    }

    public function save(int $ownerId, ?int $productId, ProductDraft $draft, ProductStatus $status, array $newImages, array $removeImageIds): int
    {
        $type = $draft->isVariable() ? 'variable' : 'simple';
        if ($productId !== null) {
            $existing = $this->owned($ownerId, $productId) ?? throw new ProductValidationException(['form' => 'Producto no encontrado.']);
            $class = \WC_Product_Factory::get_product_classname($productId, $type);
            $product = $existing->get_type() === $type ? $existing : new $class($productId);
            if ($existing->get_type() === 'variable' && $type !== 'variable') {
                foreach ($existing->get_children() as $childId) {
                    wp_delete_post($childId, true);
                }
            }
        } else {
            $product = $draft->isVariable() ? new WC_Product_Variable() : new \WC_Product_Simple();
        }

        $product->set_name($draft->name);
        if ($product->get_slug('edit') === '') {
            // WordPress leaves pending posts without a slug; publishing later must still give a clean URL.
            $product->set_slug(sanitize_title($draft->name));
        }
        $product->set_description($draft->description);
        $product->set_category_ids([$draft->categoryId]);
        $product->set_status($status->value);
        $product->set_catalog_visibility('visible');

        try {
            if ($draft->isVariable()) {
                $attribute = new WC_Product_Attribute();
                $attribute->set_name($draft->attributeName);
                $attribute->set_options(array_map(static fn ($v): string => $v->option, $draft->variations));
                $attribute->set_visible(true);
                $attribute->set_variation(true);
                $product->set_attributes([$attribute]);
                $product->set_sku('');
                $product->set_manage_stock(false);
            } else {
                $product->set_attributes([]);
                $product->set_regular_price((string) $draft->price?->toDecimal());
                $product->set_sale_price($draft->salePrice?->toDecimal() ?? '');
                $product->set_sku($draft->sku);
                $product->set_manage_stock($draft->stock !== null);
                $product->set_stock_quantity($draft->stock);
                if ($draft->stock === null) {
                    $product->set_stock_status('instock');
                }
            }
        } catch (WC_Data_Exception $e) {
            throw new ProductValidationException([str_contains($e->getErrorCode(), 'sku') ? 'sku' : 'form' => $e->getMessage()]);
        }

        $id = $product->save();
        if ($productId === null) {
            wp_update_post(['ID' => $id, 'post_author' => $ownerId]);
        }

        if ($draft->isVariable()) {
            $this->saveVariations($product, $draft);
        }
        $this->saveImages($ownerId, $product, $draft->name, $newImages, $removeImageIds);
        return $id;
    }

    public function trash(int $ownerId, int $productId): bool
    {
        $product = $this->owned($ownerId, $productId);
        return $product !== null && (bool) wp_trash_post($productId);
    }

    /**
     * @return array{items: list<WC_Product>, total: int}
     */
    public function listForVendor(int $ownerId, ?ProductStatus $status, string $search, int $page, int $perPage): array
    {
        $query = new WP_Query([
            'post_type' => 'product',
            'author' => $ownerId,
            'post_status' => $status ? [$status->value] : self::VENDOR_STATUSES,
            's' => $search,
            'orderby' => 'modified',
            'order' => 'DESC',
            'posts_per_page' => $perPage,
            'paged' => $page,
            'ignore_sticky_posts' => true,
        ]);
        $items = array_values(array_filter(array_map('wc_get_product', $query->posts)));
        return ['items' => $items, 'total' => (int) $query->found_posts];
    }

    /** Values for the edit form. */
    public function formValues(int $ownerId, int $productId): ?array
    {
        $product = $this->owned($ownerId, $productId);
        if ($product === null) {
            return null;
        }
        $values = [
            'type' => $product->is_type('variable') ? 'variable' : 'simple',
            'name' => $product->get_name('edit'),
            'description' => wp_strip_all_tags($product->get_description('edit')),
            'category' => (string) ($product->get_category_ids()[0] ?? ''),
            'price' => $product->get_regular_price('edit'),
            'sale_price' => $product->get_sale_price('edit'),
            'sku' => $product->get_sku('edit'),
            'stock' => $product->get_manage_stock() ? (string) $product->get_stock_quantity() : '',
            'attribute_name' => '',
            'variations' => [],
            'status' => $product->get_status(),
            'images' => array_map(static fn (int $id): array => [
                'id' => $id,
                'url' => (string) wp_get_attachment_image_url($id, 'thumbnail'),
            ], $this->imageIds($product)),
            'permalink' => $product->get_permalink(),
        ];
        if ($product instanceof WC_Product_Variable) {
            $attribute = array_values($product->get_attributes())[0] ?? null;
            $values['attribute_name'] = $attribute instanceof WC_Product_Attribute ? $attribute->get_name() : '';
            $key = sanitize_title($values['attribute_name']);
            foreach ($product->get_children() as $childId) {
                $variation = wc_get_product($childId);
                if (!$variation instanceof WC_Product_Variation) {
                    continue;
                }
                $values['variations'][] = [
                    'id' => (string) $childId,
                    'option' => (string) ($variation->get_attributes('edit')[$key] ?? ''),
                    'price' => $variation->get_regular_price('edit'),
                    'sale_price' => $variation->get_sale_price('edit'),
                    'sku' => $variation->get_sku('edit'),
                    'stock' => (string) ($variation->get_stock_quantity() ?? 0),
                ];
            }
        }
        return $values;
    }

    private function owned(int $ownerId, int $productId): ?WC_Product
    {
        if ($this->statusOf($ownerId, $productId) === null) {
            return null;
        }
        $product = wc_get_product($productId);
        return $product instanceof WC_Product ? $product : null;
    }

    /** @return list<int> main image first, then gallery */
    private function imageIds(WC_Product $product): array
    {
        $ids = array_map('intval', $product->get_gallery_image_ids('edit'));
        $main = (int) $product->get_image_id('edit');
        if ($main > 0) {
            array_unshift($ids, $main);
        }
        return array_values(array_unique(array_filter($ids)));
    }

    private function saveVariations(WC_Product_Variable $product, ProductDraft $draft): void
    {
        $key = sanitize_title($draft->attributeName);
        $existing = array_map('intval', $product->get_children());
        $kept = [];
        foreach ($draft->variations as $menuOrder => $v) {
            $variation = $v->id !== null && in_array($v->id, $existing, true) ? new WC_Product_Variation($v->id) : new WC_Product_Variation();
            $variation->set_parent_id($product->get_id());
            $variation->set_attributes([$key => $v->option]);
            $variation->set_regular_price($v->price->toDecimal());
            $variation->set_sale_price($v->salePrice?->toDecimal() ?? '');
            $variation->set_manage_stock(true);
            $variation->set_stock_quantity($v->stock);
            $variation->set_menu_order($menuOrder);
            $variation->set_status('publish');
            try {
                $variation->set_sku($v->sku);
            } catch (WC_Data_Exception $e) {
                throw new ProductValidationException(["variations.{$menuOrder}.sku" => $e->getMessage()]);
            }
            $kept[] = $variation->save();
        }
        foreach (array_diff($existing, $kept) as $orphan) {
            wp_delete_post($orphan, true);
        }
        WC_Product_Variable::sync($product->get_id());
    }

    /**
     * @param list<\Cheppi\Sellers\Application\UploadedFile> $newImages
     * @param list<int> $removeImageIds
     */
    private function saveImages(int $ownerId, WC_Product $product, string $title, array $newImages, array $removeImageIds): void
    {
        $ids = $this->imageIds($product);
        foreach ($removeImageIds as $removeId) {
            if (!in_array($removeId, $ids, true)) {
                continue;
            }
            $ids = array_values(array_diff($ids, [$removeId]));
            $attachment = get_post($removeId);
            if ($attachment && (int) $attachment->post_author === $ownerId && (int) $attachment->post_parent === $product->get_id()) {
                wp_delete_attachment($removeId, true); // only the vendor's own uploads are deleted from disk
            }
        }
        foreach ($newImages as $image) {
            $ids[] = $this->images->upload($image, $product->get_id(), $title);
        }
        if ($newImages === [] && $removeImageIds === []) {
            return;
        }
        $product->set_image_id($ids[0] ?? 0);
        $product->set_gallery_image_ids(array_slice($ids, 1));
        $product->save();
    }
}
