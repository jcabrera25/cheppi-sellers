<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Catalog;

use Cheppi\Sellers\Application\ProductCatalog;
use Cheppi\Sellers\Domain\Catalog\ProductChoices;
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

    public function choices(int $ownerId, ?int $productId): ProductChoices
    {
        $brands = [];
        if (taxonomy_exists('product_brand')) {
            $terms = get_terms(['taxonomy' => 'product_brand', 'hide_empty' => false, 'fields' => 'id=>name', 'orderby' => 'name']);
            $brands = is_array($terms) ? array_map('strval', $terms) : [];
        }
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'product' AND post_author = %d AND post_status IN ('publish','pending','draft') AND ID <> %d ORDER BY post_title LIMIT 300",
            $ownerId,
            (int) $productId
        ));
        $linkable = [];
        foreach ((array) $rows as $row) {
            $linkable[(int) $row->ID] = (string) $row->post_title;
        }
        return new ProductChoices($this->categories(), $brands, $linkable);
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
        $product->set_short_description(self::cleanHtml($draft->shortDescription));
        $product->set_description(self::cleanHtml($draft->description));
        $product->set_category_ids($draft->categoryIds);
        $product->set_status($status->value);
        $product->set_catalog_visibility('visible');
        $product->set_sold_individually($draft->soldIndividually);
        $product->set_purchase_note(sanitize_textarea_field($draft->purchaseNote));
        $product->set_upsell_ids($draft->upsellIds);
        $product->set_cross_sell_ids($draft->crossSellIds);
        $product->set_weight($draft->shipping['weight'] ?? '');
        $product->set_length($draft->shipping['length'] ?? '');
        $product->set_width($draft->shipping['width'] ?? '');
        $product->set_height($draft->shipping['height'] ?? '');
        $product->set_low_stock_amount($draft->lowStock ?? '');
        $product->set_backorders($draft->backorders);

        $attributes = [];
        foreach ($draft->attributes as $position => $a) {
            $attribute = new WC_Product_Attribute();
            $attribute->set_name($a->name);
            $attribute->set_options($a->options);
            $attribute->set_position($position);
            $attribute->set_visible($a->visible);
            $attribute->set_variation($a->forVariations);
            $attributes[] = $attribute;
        }
        $product->set_attributes($attributes);

        try {
            $product->set_sku($draft->sku);
            if ($draft->isVariable()) {
                $product->set_manage_stock($draft->manageStock);
                $product->set_stock_quantity($draft->manageStock ? $draft->stock : null);
                $product->set_virtual(false);
            } else {
                $product->set_virtual($draft->virtual);
                $product->set_regular_price((string) $draft->price?->toDecimal());
                $product->set_sale_price($draft->salePrice?->toDecimal() ?? '');
                $product->set_date_on_sale_from($draft->saleFrom?->format('Y-m-d 00:00:00') ?? '');
                $product->set_date_on_sale_to($draft->saleTo?->format('Y-m-d 23:59:59') ?? '');
                $product->set_manage_stock($draft->manageStock);
                $product->set_stock_quantity($draft->manageStock ? $draft->stock : null);
                if (!$draft->manageStock) {
                    $product->set_stock_status($draft->stockStatus);
                }
            }
        } catch (WC_Data_Exception $e) {
            throw new ProductValidationException([str_contains($e->getErrorCode(), 'sku') ? 'sku' : 'form' => $e->getMessage()]);
        }

        $id = $product->save();
        if ($productId === null) {
            wp_update_post(['ID' => $id, 'post_author' => $ownerId]);
        }

        wp_set_object_terms($id, $draft->tags, 'product_tag');
        if (taxonomy_exists('product_brand')) {
            wp_set_object_terms($id, $draft->brandIds, 'product_brand');
        }
        $this->saveImages($ownerId, $product, $draft->name, $newImages, $removeImageIds);
        if ($draft->isVariable()) {
            $this->saveVariations($product, $draft);
        }
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
    public function listForVendor(int $ownerId, ?ProductStatus $status, string $search, int $page, int $perPage, ?ProductListQuery $filters = null): array
    {
        $tax = $filters?->taxQuery() ?? [];
        $query = new WP_Query(($tax !== [] ? ['tax_query' => $tax] : []) + [ // phpcs:ignore WordPress.DB.SlowDBQuery
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

    /** Values for the edit form, with the same keys the form posts. */
    public function formValues(int $ownerId, int $productId): ?array
    {
        $product = $this->owned($ownerId, $productId);
        if ($product === null) {
            return null;
        }
        $date = static fn ($d): string => $d instanceof \WC_DateTime ? $d->date('Y-m-d') : '';
        $values = [
            'type' => $product->is_type('variable') ? 'variable' : 'simple',
            'name' => $product->get_name('edit'),
            'short_description' => $product->get_short_description('edit'),
            'description' => $product->get_description('edit'),
            'categories' => array_map('strval', $product->get_category_ids('edit')),
            'brands' => array_map('strval', taxonomy_exists('product_brand') ? wp_get_object_terms($productId, 'product_brand', ['fields' => 'ids']) : []),
            'tags' => implode(', ', wp_get_object_terms($productId, 'product_tag', ['fields' => 'names'])),
            'price' => $product->get_regular_price('edit'),
            'sale_price' => $product->get_sale_price('edit'),
            'sale_from' => $date($product->get_date_on_sale_from('edit')),
            'sale_to' => $date($product->get_date_on_sale_to('edit')),
            'sku' => $product->get_sku('edit'),
            'manage_stock' => $product->get_manage_stock('edit') ? '1' : '',
            'stock' => $product->get_manage_stock('edit') ? (string) $product->get_stock_quantity('edit') : '',
            'stock_status' => $product->get_stock_status('edit'),
            'backorders' => $product->get_backorders('edit'),
            'low_stock' => (string) $product->get_low_stock_amount('edit'),
            'sold_individually' => $product->get_sold_individually('edit') ? '1' : '',
            'virtual' => $product->get_virtual('edit') ? '1' : '',
            'weight' => $product->get_weight('edit'),
            'length' => $product->get_length('edit'),
            'width' => $product->get_width('edit'),
            'height' => $product->get_height('edit'),
            'upsells' => array_map('strval', $product->get_upsell_ids('edit')),
            'cross_sells' => array_map('strval', $product->get_cross_sell_ids('edit')),
            'purchase_note' => $product->get_purchase_note('edit'),
            'attributes' => [],
            'variations' => [],
            'status' => $product->get_status(),
            'images' => array_map(static fn (int $id): array => [
                'id' => $id,
                'url' => (string) wp_get_attachment_image_url($id, 'thumbnail'),
            ], $this->imageIds($product)),
            'permalink' => $product->get_permalink(),
        ];
        $variationKeys = [];
        foreach ($product->get_attributes('edit') as $attribute) {
            if (!$attribute instanceof WC_Product_Attribute) {
                continue;
            }
            $names = $attribute->is_taxonomy()
                ? array_map(static fn ($t): string => $t->name, $attribute->get_terms() ?: [])
                : $attribute->get_options();
            $label = $attribute->is_taxonomy() ? wc_attribute_label($attribute->get_name()) : $attribute->get_name();
            $values['attributes'][] = [
                'name' => $label,
                'options' => implode(' | ', $names),
                'visible' => $attribute->get_visible() ? '1' : '',
                'variation' => $attribute->get_variation() ? '1' : '',
            ];
            if ($attribute->get_variation()) {
                $variationKeys[] = sanitize_title($attribute->get_name());
            }
        }
        if ($product instanceof WC_Product_Variable) {
            foreach ($product->get_children() as $childId) {
                $variation = wc_get_product($childId);
                if (!$variation instanceof WC_Product_Variation) {
                    continue;
                }
                $chosen = $variation->get_attributes('edit');
                $values['variations'][] = [
                    'id' => (string) $childId,
                    'attributes' => array_map(static fn (string $key): string => (string) ($chosen[$key] ?? ''), $variationKeys),
                    'enabled' => $variation->get_status('edit') === 'private' ? '' : '1',
                    'price' => $variation->get_regular_price('edit'),
                    'sale_price' => $variation->get_sale_price('edit'),
                    'sku' => $variation->get_sku('edit'),
                    'manage_stock' => $variation->get_manage_stock('edit') === true ? '1' : '',
                    'stock' => (string) ($variation->get_stock_quantity('edit') ?? ''),
                    'stock_status' => $variation->get_stock_status('edit'),
                    'weight' => $variation->get_weight('edit'),
                    'image_id' => (string) $variation->get_image_id('edit'),
                    'description' => $variation->get_description('edit'),
                ];
            }
        }
        return $values;
    }

    /** Safe rich text for descriptions: paragraphs, emphasis, lists and links only. */
    private static function cleanHtml(string $html): string
    {
        return trim(wp_kses($html, [
            'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [],
            'ul' => [], 'ol' => [], 'li' => [], 'h3' => [], 'h4' => [], 'blockquote' => [],
            'a' => ['href' => true, 'target' => true, 'rel' => true],
        ]));
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
        $existing = array_map('intval', $product->get_children());
        $images = $this->imageIds(wc_get_product($product->get_id()) ?: $product);
        $kept = [];
        $default = [];
        foreach ($draft->variations as $menuOrder => $v) {
            $variation = $v->id !== null && in_array($v->id, $existing, true) ? new WC_Product_Variation($v->id) : new WC_Product_Variation();
            $variation->set_parent_id($product->get_id());
            $keys = [];
            foreach ($v->attributes as $name => $option) {
                $keys[sanitize_title($name)] = $option;
            }
            $variation->set_attributes($keys);
            $variation->set_status($v->enabled ? 'publish' : 'private');
            $variation->set_regular_price($v->price->toDecimal());
            $variation->set_sale_price($v->salePrice?->toDecimal() ?? '');
            $variation->set_manage_stock($v->manageStock);
            $variation->set_stock_quantity($v->manageStock ? $v->stock : null);
            if (!$v->manageStock) {
                $variation->set_stock_status($v->stockStatus);
            }
            $variation->set_weight($v->weight ?? '');
            $variation->set_image_id(in_array($v->imageId, $images, true) ? $v->imageId : 0); // only this product's photos
            $variation->set_description(sanitize_textarea_field($v->description));
            $variation->set_menu_order($menuOrder);
            try {
                $variation->set_sku($v->sku);
            } catch (WC_Data_Exception $e) {
                throw new ProductValidationException(["variations.{$menuOrder}.sku" => $e->getMessage()]);
            }
            $kept[] = $variation->save();
            if ($default === [] && $v->enabled) {
                $default = $keys;
            }
        }
        foreach (array_diff($existing, $kept) as $orphan) {
            wp_delete_post($orphan, true);
        }
        $product->set_default_attributes($default);
        $product->save();
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
