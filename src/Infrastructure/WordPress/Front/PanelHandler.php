<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Front;

use Cheppi\Sellers\Application\SaveVendorProduct;
use Cheppi\Sellers\Application\UploadedFile;
use Cheppi\Sellers\Application\VendorRepository;
use Cheppi\Sellers\Domain\Catalog\ProductStatus;
use Cheppi\Sellers\Domain\Catalog\ProductValidationException;
use Cheppi\Sellers\Domain\Vendor\VendorValidationException;
use Cheppi\Sellers\Infrastructure\WordPress\Catalog\ImageUploader;
use Cheppi\Sellers\Infrastructure\WordPress\Catalog\WcProductCatalog;
use DateTimeImmutable;

/**
 * Seller panel forms (admin-post.php): save/delete product, save storefront.
 * Every action checks the nonce, the signed-in vendor and ownership.
 */
final class PanelHandler
{
    public const SAVE_PRODUCT = 'cheppi_product_save';
    public const DELETE_PRODUCT = 'cheppi_product_delete';
    public const SAVE_STORE = 'cheppi_store_save';

    public function __construct(
        private readonly SaveVendorProduct $saveProduct,
        private readonly WcProductCatalog $catalog,
        private readonly VendorRepository $vendors,
        private readonly ImageUploader $images
    ) {
    }

    public function register(): void
    {
        add_action('admin_post_' . self::SAVE_PRODUCT, [$this, 'saveProduct']);
        add_action('admin_post_' . self::DELETE_PRODUCT, [$this, 'deleteProduct']);
        add_action('admin_post_' . self::SAVE_STORE, [$this, 'saveStore']);
    }

    public function saveProduct(): void
    {
        PanelController::sendNoCacheHeaders();
        $productId = absint($_POST['product_id'] ?? 0);
        check_admin_referer(self::SAVE_PRODUCT . $productId);
        $post = wp_unslash($_POST);

        $input = [
            'type' => sanitize_key((string) ($post['type'] ?? 'simple')),
            'name' => sanitize_text_field((string) ($post['name'] ?? '')),
            'description' => sanitize_textarea_field((string) ($post['description'] ?? '')),
            'category' => absint($post['category'] ?? 0),
            'price' => sanitize_text_field((string) ($post['price'] ?? '')),
            'sale_price' => sanitize_text_field((string) ($post['sale_price'] ?? '')),
            'sku' => sanitize_text_field((string) ($post['sku'] ?? '')),
            'stock' => sanitize_text_field((string) ($post['stock'] ?? '')),
            'attribute_name' => sanitize_text_field((string) ($post['attribute_name'] ?? '')),
            'variations' => [],
        ];
        foreach ((array) ($post['variations'] ?? []) as $row) {
            if (is_array($row)) {
                $input['variations'][] = array_map(static fn ($v): string => sanitize_text_field((string) $v), array_intersect_key(
                    $row,
                    array_flip(['id', 'option', 'price', 'sale_price', 'sku', 'stock'])
                ));
            }
        }
        $remove = array_values(array_filter(array_map('absint', (array) ($post['remove_images'] ?? []))));
        $draft = ($post['intent'] ?? '') === 'draft';

        try {
            $result = ($this->saveProduct)(get_current_user_id(), $productId ?: null, $input, self::images(), $remove, $draft);
        } catch (ProductValidationException $e) {
            Flash::set(['type' => 'error', 'message' => $e->errors['form'] ?? __('No se guardó: revisa los campos marcados.', 'cheppi-sellers'), 'errors' => $e->errors, 'values' => $input]);
            $this->back(Routes::productUrl($productId));
        }

        $message = match ($result['status']) {
            ProductStatus::Draft => __('Guardado como borrador.', 'cheppi-sellers'),
            ProductStatus::Pending => __('Enviado a revisión. Te avisaremos cuando esté publicado.', 'cheppi-sellers'),
            ProductStatus::Published => __('Producto publicado.', 'cheppi-sellers'),
        };
        Flash::set(['type' => 'success', 'message' => $message]);
        $this->back(Routes::productUrl($result['id']));
    }

    public function deleteProduct(): void
    {
        $productId = absint($_POST['product_id'] ?? 0);
        check_admin_referer(self::DELETE_PRODUCT . $productId);
        $ok = $this->catalog->trash(get_current_user_id(), $productId);
        Flash::set($ok
            ? ['type' => 'success', 'message' => __('Producto eliminado. Si fue un error, escríbenos para recuperarlo.', 'cheppi-sellers')]
            : ['type' => 'error', 'message' => __('Producto no encontrado.', 'cheppi-sellers')]);
        $this->back(Routes::productsUrl());
    }

    public function saveStore(): void
    {
        check_admin_referer(self::SAVE_STORE);
        $vendor = $this->vendors->findByUserId(get_current_user_id());
        if ($vendor === null || !$vendor->isPublic()) {
            $this->back(Routes::panelUrl());
        }
        $post = wp_unslash($_POST);
        $logo = !empty($post['remove_logo']) ? 0 : $vendor->logoId;
        $banner = !empty($post['remove_banner']) ? 0 : $vendor->bannerId;

        try {
            $newLogo = self::singleImage('logo');
            if ($newLogo !== null) {
                $newLogo->assertImage('logo');
                $logo = $this->images->upload($newLogo, 0, $vendor->storeName . ' logo');
            }
            $newBanner = self::singleImage('banner');
            if ($newBanner !== null) {
                $newBanner->assertImage('banner');
                $banner = $this->images->upload($newBanner, 0, $vendor->storeName . ' banner');
            }
            $updated = $vendor->withStorefront($logo, $banner, sanitize_textarea_field((string) ($post['about'] ?? '')), new DateTimeImmutable('now'));
            $this->vendors->save($updated);
            Flash::set(['type' => 'success', 'message' => __('Tu tienda se actualizó.', 'cheppi-sellers')]);
        } catch (ProductValidationException | VendorValidationException $e) {
            Flash::set(['type' => 'error', 'message' => implode(' ', $e->errors), 'errors' => $e->errors]);
        }
        $this->back(Routes::storeSettingsUrl());
    }

    private function back(string $url): never
    {
        wp_safe_redirect($url);
        exit;
    }

    /** @return list<UploadedFile> product photos from the multi-file input "images[]" */
    private static function images(): array
    {
        $raw = $_FILES['images'] ?? null;
        if (!is_array($raw) || !is_array($raw['name'] ?? null)) {
            return [];
        }
        $files = [];
        foreach ($raw['name'] as $i => $name) {
            if (($raw['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $files[] = self::toUploaded((string) $name, (string) $raw['tmp_name'][$i], (int) $raw['error'][$i], (int) $raw['size'][$i]);
        }
        return $files;
    }

    private static function singleImage(string $field): ?UploadedFile
    {
        $raw = $_FILES[$field] ?? null;
        if (!is_array($raw) || ($raw['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return self::toUploaded((string) $raw['name'], (string) $raw['tmp_name'], (int) $raw['error'], (int) $raw['size']);
    }

    private static function toUploaded(string $name, string $tmp, int $error, int $size): UploadedFile
    {
        $ok = $error === UPLOAD_ERR_OK && $tmp !== '' && is_uploaded_file($tmp);
        $mime = $ok ? (string) (wp_get_image_mime($tmp) ?: '') : '';
        return new UploadedFile($tmp, $name, $mime, $ok ? $size : 0);
    }
}
