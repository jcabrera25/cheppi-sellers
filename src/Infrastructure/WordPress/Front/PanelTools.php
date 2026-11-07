<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Front;

use Cheppi\Sellers\Application\SaveVendorProduct;
use Cheppi\Sellers\Application\UploadedFile;
use Cheppi\Sellers\Domain\Catalog\ProductSheet;
use Cheppi\Sellers\Domain\Catalog\ProductValidationException;
use Cheppi\Sellers\Domain\Payout\BankDetails;
use Cheppi\Sellers\Domain\Promotion\CouponDraft;
use Cheppi\Sellers\Domain\Promotion\PromotionValidationException;
use Cheppi\Sellers\Domain\Vendor\Vendor;
use Cheppi\Sellers\Domain\Vendor\VendorValidationException;
use Cheppi\Sellers\Infrastructure\WordPress\Catalog\WcProductCatalog;
use Cheppi\Sellers\Infrastructure\WordPress\Mail\WpMailNotifier;
use Cheppi\Sellers\Infrastructure\WordPress\Payouts\PayoutFiles;
use Cheppi\Sellers\Infrastructure\WordPress\Persistence\WpVendorExtras;
use Cheppi\Sellers\Infrastructure\WordPress\Persistence\WpVendorRepository;
use Cheppi\Sellers\Infrastructure\WordPress\Promotions\Campaigns;
use Cheppi\Sellers\Infrastructure\WordPress\Promotions\FeaturedProducts;
use Cheppi\Sellers\Infrastructure\WordPress\Promotions\VendorCoupons;
use DateTimeImmutable;

/**
 * Seller panel forms of phases 4–5 (admin-post.php): bank account, coupons, campaigns, featured products,
 * spreadsheet import/export and the premium page request. Each checks nonce and the signed-in vendor.
 */
final class PanelTools
{
    public const SAVE_BANK = 'cheppi_bank_save';
    public const COUPON_CREATE = 'cheppi_coupon_create';
    public const COUPON_DELETE = 'cheppi_coupon_delete';
    public const CAMPAIGN_TOGGLE = 'cheppi_campaign_toggle';
    public const FEATURE_BUY = 'cheppi_feature_buy';
    public const IMPORT = 'cheppi_products_import';
    public const EXPORT = 'cheppi_products_export';
    public const TEMPLATE = 'cheppi_products_template';
    public const PREMIUM_REQUEST = 'cheppi_premium_request';
    private const MAX_IMAGE_DOWNLOADS = 30;

    public function __construct(
        private readonly WpVendorRepository $vendors,
        private readonly WpVendorExtras $extras,
        private readonly VendorCoupons $coupons,
        private readonly Campaigns $campaigns,
        private readonly FeaturedProducts $featured,
        private readonly SaveVendorProduct $saveProduct,
        private readonly WcProductCatalog $catalog,
        private readonly WpMailNotifier $mailer
    ) {
    }

    public function register(): void
    {
        foreach ([
            self::SAVE_BANK => 'saveBank',
            self::COUPON_CREATE => 'createCoupon',
            self::COUPON_DELETE => 'deleteCoupon',
            self::CAMPAIGN_TOGGLE => 'toggleCampaign',
            self::FEATURE_BUY => 'buyFeatured',
            self::IMPORT => 'import',
            self::EXPORT => 'export',
            self::TEMPLATE => 'template',
            self::PREMIUM_REQUEST => 'requestPremium',
        ] as $action => $method) {
            add_action('admin_post_' . $action, [$this, $method]);
        }
    }

    public static function downloadUrl(string $action): string
    {
        return wp_nonce_url(admin_url('admin-post.php?action=' . $action), $action);
    }

    public function saveBank(): void
    {
        $vendor = $this->vendor(self::SAVE_BANK);
        $input = array_map('sanitize_text_field', wp_unslash(array_intersect_key($_POST, array_flip(['bank', 'holder', 'account', 'cci']))));
        try {
            $this->extras->saveBank((int) $vendor->id, BankDetails::fromInput($input));
            Flash::set(['type' => 'success', 'message' => __('Tu cuenta bancaria se guardó.', 'cheppi-sellers')]);
        } catch (VendorValidationException $e) {
            Flash::set(['type' => 'error', 'message' => __('Revisa los datos de tu cuenta.', 'cheppi-sellers'), 'errors' => $e->errors, 'values' => $input]);
        }
        $this->back(Routes::paymentsUrl());
    }

    public function createCoupon(): void
    {
        $vendor = $this->vendor(self::COUPON_CREATE);
        $input = array_map('sanitize_text_field', wp_unslash(array_intersect_key($_POST, array_flip(['code', 'type', 'amount', 'minimum', 'expires', 'limit']))));
        try {
            $draft = CouponDraft::fromInput($input, wp_timezone(), new DateTimeImmutable('today', wp_timezone()));
            $this->coupons->create($vendor, $draft);
            Flash::set(['type' => 'success', 'message' => sprintf(__('Cupón %s creado. Compártelo con tus clientes.', 'cheppi-sellers'), $draft->code)]);
        } catch (PromotionValidationException $e) {
            Flash::set(['type' => 'error', 'message' => __('Revisa el cupón.', 'cheppi-sellers'), 'errors' => $e->errors, 'values' => $input]);
        }
        $this->back(Routes::promotionsUrl());
    }

    public function deleteCoupon(): void
    {
        $vendor = $this->vendor(self::COUPON_DELETE);
        $ok = $this->coupons->delete($vendor, absint($_POST['coupon_id'] ?? 0));
        Flash::set($ok ? ['type' => 'success', 'message' => __('Cupón desactivado.', 'cheppi-sellers')] : ['type' => 'error', 'message' => __('Cupón no encontrado.', 'cheppi-sellers')]);
        $this->back(Routes::promotionsUrl());
    }

    public function toggleCampaign(): void
    {
        $vendor = $this->vendor(self::CAMPAIGN_TOGGLE);
        $id = sanitize_key((string) ($_POST['campaign'] ?? ''));
        $campaign = $this->campaigns->find($id);
        $joined = $this->extras->campaigns((int) $vendor->id);
        if ($campaign === null || $campaign->isOver(new DateTimeImmutable('now'))) {
            Flash::set(['type' => 'error', 'message' => __('La campaña ya terminó.', 'cheppi-sellers')]);
        } elseif (in_array($id, $joined, true)) {
            $this->extras->saveCampaigns((int) $vendor->id, array_values(array_diff($joined, [$id])));
            Flash::set(['type' => 'success', 'message' => sprintf(__('Saliste de «%s».', 'cheppi-sellers'), $campaign->name)]);
        } else {
            // Keep only campaigns that are still open, so the list never grows.
            $open = array_map(static fn ($c): string => $c->id, $this->campaigns->open(new DateTimeImmutable('now')));
            $this->extras->saveCampaigns((int) $vendor->id, array_values(array_intersect(array_merge($joined, [$id]), $open)));
            Flash::set(['type' => 'success', 'message' => sprintf(__('Te uniste a «%s».', 'cheppi-sellers'), $campaign->name)]);
        }
        $this->back(Routes::promotionsUrl());
    }

    public function buyFeatured(): void
    {
        $vendor = $this->vendor(self::FEATURE_BUY);
        try {
            $spot = $this->featured->buy($vendor, absint($_POST['product_id'] ?? 0), sanitize_text_field((string) ($_POST['days'] ?? '')), new DateTimeImmutable('now'));
            Flash::set(['type' => 'success', 'message' => sprintf(__('Producto destacado hasta el %1$s. Se descontarán S/ %2$s de tu próxima liquidación.', 'cheppi-sellers'), wp_date('d/m/Y', $spot->until->getTimestamp()), $spot->price->toDecimal())]);
        } catch (PromotionValidationException $e) {
            Flash::set(['type' => 'error', 'message' => implode(' ', $e->errors)]);
        }
        $this->back(Routes::promotionsUrl());
    }

    public function requestPremium(): void
    {
        $vendor = $this->vendor(self::PREMIUM_REQUEST);
        $current = $this->extras->premium((int) $vendor->id);
        if ($current['status'] === '') {
            $this->extras->savePremium((int) $vendor->id, 0, WpVendorExtras::PREMIUM_REQUESTED);
            $this->mailer->premiumRequested($vendor, mb_substr(sanitize_textarea_field(wp_unslash((string) ($_POST['message'] ?? ''))), 0, 1000));
        }
        Flash::set(['type' => 'success', 'message' => __('Recibimos tu solicitud. Te escribiremos para coordinar el diseño.', 'cheppi-sellers')]);
        $this->back(Routes::storeSettingsUrl());
    }

    /** Spreadsheet template with one example row. */
    public function template(): void
    {
        $this->vendor(self::TEMPLATE, true);
        PayoutFiles::sendCsv('plantilla-productos-cheppi.csv', [
            ProductSheet::HEADERS,
            ['LAB-001', 'Labial mate rojo', 'Larga duración, 12 horas', 'Labios', '39.90', '34.90', '20', 'https://tusitio.com/foto-labial.jpg'],
        ]);
    }

    public function export(): void
    {
        $vendor = $this->vendor(self::EXPORT, true);
        $categories = $this->catalog->categories();
        $rows = [array_merge(ProductSheet::HEADERS, ['tipo', 'estado'])];
        foreach (wc_get_products(['author' => $vendor->userId, 'status' => ['publish', 'pending', 'draft'], 'limit' => 1000, 'orderby' => 'date', 'order' => 'DESC']) as $product) {
            $image = $product->get_image_id() ? (string) wp_get_attachment_url($product->get_image_id()) : '';
            $rows[] = [
                $product->get_sku(),
                $product->get_name(),
                wp_strip_all_tags($product->get_description()),
                $categories[$product->get_category_ids()[0] ?? 0] ?? '',
                (string) $product->get_regular_price(),
                (string) $product->get_sale_price(),
                $product->get_manage_stock() ? (string) $product->get_stock_quantity() : '',
                $image,
                $product->get_type() === 'variable' ? 'variable (no se importa)' : 'simple',
                $product->get_status(),
            ];
        }
        PayoutFiles::sendCsv('productos-' . $vendor->storeSlug . '-' . wp_date('Y-m-d') . '.csv', $rows);
    }

    /** Creates or updates (by SKU) up to ProductSheet::MAX_ROWS simple products from a CSV. */
    public function import(): void
    {
        $vendor = $this->vendor(self::IMPORT);
        $file = $_FILES['sheet'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? 1) !== UPLOAD_ERR_OK || (int) $file['size'] > 2 * MB_IN_BYTES) {
            Flash::set(['type' => 'error', 'message' => __('Sube un archivo CSV de hasta 2 MB.', 'cheppi-sellers')]);
            $this->back(Routes::importUrl());
        }
        $sheet = ProductSheet::parse((string) file_get_contents((string) $file['tmp_name']));
        $categories = $this->catalog->categories();
        $report = [];
        $downloads = 0;
        require_once ABSPATH . 'wp-admin/includes/file.php';

        foreach ($sheet['rows'] as $row) {
            $input = ProductSheet::toInput($row['data'], $categories);
            $existing = $input['sku'] !== '' ? (int) wc_get_product_id_by_sku($input['sku']) : 0;
            if ($existing > 0 && (int) get_post_field('post_author', $existing) !== $vendor->userId) {
                $report[] = [$row['line'], 'error', sprintf(__('El SKU %s ya lo usa otra tienda.', 'cheppi-sellers'), $input['sku'])];
                continue;
            }
            $images = [];
            $url = $row['data']['image_url'] ?? '';
            if ($url !== '' && wp_http_validate_url($url) && $downloads < self::MAX_IMAGE_DOWNLOADS) {
                $downloads++;
                $tmp = download_url($url, 15);
                if (!is_wp_error($tmp)) {
                    $type = wp_check_filetype(basename((string) wp_parse_url($url, PHP_URL_PATH)));
                    $mime = (string) (wp_get_image_mime($tmp) ?: ($type['type'] ?? ''));
                    $images[] = new UploadedFile($tmp, sanitize_file_name(basename((string) wp_parse_url($url, PHP_URL_PATH))) ?: 'foto.jpg', $mime, (int) filesize($tmp));
                } else {
                    $report[] = [$row['line'], 'aviso', sprintf(__('No se pudo descargar la foto: %s', 'cheppi-sellers'), $tmp->get_error_message())];
                }
            }
            $hasPhotos = $images !== [] || ($existing > 0 && $this->catalog->imageCount($vendor->userId, $existing) > 0);
            try {
                $result = ($this->saveProduct)($vendor->userId, $existing ?: null, $input, $images, [], !$hasPhotos);
                $report[] = [$row['line'], $existing > 0 ? 'actualizado' : 'creado', sprintf('%s · %s', $input['name'], $result['status']->label())];
            } catch (ProductValidationException $e) {
                $report[] = [$row['line'], 'error', $input['name'] . ': ' . implode(' ', $e->errors)];
            }
            foreach ($images as $image) {
                if (is_file($image->tmpPath)) {
                    wp_delete_file($image->tmpPath);
                }
            }
        }
        $ok = count(array_filter($report, static fn (array $r): bool => in_array($r[1], ['creado', 'actualizado'], true)));
        Flash::set([
            'type' => $ok > 0 ? 'success' : 'error',
            'message' => sprintf(__('%1$d de %2$d filas importadas.', 'cheppi-sellers'), $ok, count($sheet['rows'])),
            'report' => array_slice($report, 0, 150),
            'sheetErrors' => $sheet['errors'],
        ]);
        $this->back(Routes::importUrl());
    }

    private function vendor(string $nonceAction, bool $get = false): Vendor
    {
        $valid = $get ? wp_verify_nonce((string) ($_GET['_wpnonce'] ?? ''), $nonceAction) : wp_verify_nonce((string) ($_POST['_wpnonce'] ?? ''), $nonceAction);
        $vendor = $this->vendors->findByUserId(get_current_user_id());
        if (!$valid || $vendor === null || !$vendor->isPublic()) {
            wp_die(esc_html__('No tienes permiso para hacer esto.', 'cheppi-sellers'), 403);
        }
        PanelController::sendNoCacheHeaders();
        return $vendor;
    }

    private function back(string $url): never
    {
        wp_safe_redirect($url);
        exit;
    }
}
