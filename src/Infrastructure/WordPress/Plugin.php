<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress;

use Cheppi\Sellers\Application\CreateVendorByAdmin;
use Cheppi\Sellers\Application\DeleteVendor;
use Cheppi\Sellers\Application\ResubmitVendorDocuments;
use Cheppi\Sellers\Application\ReviewVendor;
use Cheppi\Sellers\Application\RecordOrderSales;
use Cheppi\Sellers\Application\ReverseOrderSales;
use Cheppi\Sellers\Application\SaveVendorProduct;
use Cheppi\Sellers\Application\UpdateFulfillment;
use Cheppi\Sellers\Application\SubmitVendorApplication;
use Cheppi\Sellers\Application\UpdateVendorProfile;
use Cheppi\Sellers\Infrastructure\WordPress\Admin\BusinessRulesPage;
use Cheppi\Sellers\Infrastructure\WordPress\Admin\OrderVendorsBox;
use Cheppi\Sellers\Infrastructure\WordPress\Admin\ProductModeration;
use Cheppi\Sellers\Infrastructure\WordPress\Admin\VendorsPage;
use Cheppi\Sellers\Infrastructure\WordPress\Catalog\ImageUploader;
use Cheppi\Sellers\Infrastructure\WordPress\Catalog\VendorStats;
use Cheppi\Sellers\Infrastructure\WordPress\Catalog\WcProductCatalog;
use Cheppi\Sellers\Infrastructure\WordPress\Front\ApplicationHandler;
use Cheppi\Sellers\Infrastructure\WordPress\Front\PanelController;
use Cheppi\Sellers\Infrastructure\WordPress\Front\OrdersHandler;
use Cheppi\Sellers\Infrastructure\WordPress\Front\PanelHandler;
use Cheppi\Sellers\Infrastructure\WordPress\Front\Routes;
use Cheppi\Sellers\Infrastructure\WordPress\Front\Shortcodes;
use Cheppi\Sellers\Infrastructure\WordPress\Front\StoreController;
use Cheppi\Sellers\Infrastructure\WordPress\Mail\ProductPublishedNotifier;
use Cheppi\Sellers\Infrastructure\WordPress\Mail\WpMailNotifier;
use Cheppi\Sellers\Infrastructure\WordPress\Persistence\WpBusinessRulesRepository;
use Cheppi\Sellers\Infrastructure\WordPress\Orders\OrderSync;
use Cheppi\Sellers\Infrastructure\WordPress\Persistence\WpDeliveryZones;
use Cheppi\Sellers\Infrastructure\WordPress\Persistence\WpDocumentStore;
use Cheppi\Sellers\Infrastructure\WordPress\Persistence\WpFulfillments;
use Cheppi\Sellers\Infrastructure\WordPress\Persistence\WpLedger;
use Cheppi\Sellers\Infrastructure\WordPress\Persistence\WpVendorActivity;
use Cheppi\Sellers\Infrastructure\WordPress\Persistence\WpVendorRepository;
use Cheppi\Sellers\Infrastructure\WordPress\Rest\RulesController;
use Cheppi\Sellers\Infrastructure\WordPress\Shipping\CartShipping;

/**
 * Composition root: wires domain services to WordPress. Holds no business logic.
 */
final class Plugin
{
    private static ?self $instance = null;
    private bool $booted = false;
    private readonly WpBusinessRulesRepository $rules;
    private readonly WpVendorRepository $vendors;
    private readonly WpDocumentStore $documents;
    private readonly WpMailNotifier $notifier;
    private readonly Assets $assets;
    private readonly WpLedger $ledger;
    private readonly WpFulfillments $fulfillments;
    private readonly WpDeliveryZones $zones;

    private function __construct()
    {
        $this->rules = new WpBusinessRulesRepository();
        $this->vendors = new WpVendorRepository();
        $this->documents = new WpDocumentStore();
        $this->notifier = new WpMailNotifier();
        $this->assets = new Assets();
        $this->ledger = new WpLedger();
        $this->fulfillments = new WpFulfillments();
        $this->zones = new WpDeliveryZones();
    }

    /** Used by the WooCommerce shipping method, which WooCommerce instantiates itself. */
    public function deliveryZones(): WpDeliveryZones
    {
        return $this->zones;
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function rules(): WpBusinessRulesRepository
    {
        return $this->rules;
    }

    public function vendors(): WpVendorRepository
    {
        return $this->vendors;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        load_plugin_textdomain('cheppi-sellers', false, dirname(plugin_basename(CHEPPI_SELLERS_FILE)) . '/languages');

        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', static function (): void {
                echo '<div class="notice notice-error"><p>'
                    . esc_html__('Cheppi Sellers necesita WooCommerce activo.', 'cheppi-sellers')
                    . '</p></div>';
            });
            return;
        }

        Installer::maybeUpgrade();
        add_action('init', [Routes::class, 'register']);

        $images = new ImageUploader();
        $catalog = new WcProductCatalog($images);

        $panel = new PanelController($this->vendors, $this->documents, $this->rules, $catalog, new VendorStats(), $this->fulfillments, $this->ledger, $this->zones, $this->assets);
        $panel->register();
        (new StoreController($this->vendors, $this->assets))->register();
        (new ProductPublishedNotifier($this->vendors, $this->notifier))->register();
        (new CartShipping($this->vendors, $this->zones))->register();
        (new OrderSync(
            $this->vendors,
            $this->rules,
            new RecordOrderSales($this->ledger, $this->fulfillments),
            new ReverseOrderSales($this->ledger, $this->fulfillments),
            $this->fulfillments,
            $this->notifier
        ))->register();
        (new OrdersHandler(
            new UpdateFulfillment($this->fulfillments, $this->ledger, $this->rules),
            $this->fulfillments,
            $this->zones,
            $this->vendors
        ))->register();

        (new PanelHandler(
            new SaveVendorProduct($this->vendors, $catalog, $this->rules),
            $catalog,
            $this->vendors,
            $images
        ))->register();

        (new ApplicationHandler(
            new SubmitVendorApplication($this->vendors, $this->documents, $this->rules, $this->notifier),
            new ResubmitVendorDocuments($this->vendors, $this->documents, $this->notifier),
            $this->vendors,
            $panel
        ))->register();

        (new Shortcodes($this->rules, $this->assets))->register();

        if (is_admin()) {
            (new BusinessRulesPage($this->rules, $this->assets))->register();
            (new VendorsPage(
                $this->vendors,
                $this->documents,
                $this->rules,
                new ReviewVendor($this->vendors, $this->rules, $this->notifier),
                new CreateVendorByAdmin($this->vendors, $this->documents, $this->rules),
                new UpdateVendorProfile($this->vendors),
                new DeleteVendor($this->vendors, $this->documents, new WpVendorActivity()),
                $this->assets
            ))->register();
            (new ProductModeration($this->vendors))->register();
            (new OrderVendorsBox($this->fulfillments, $this->ledger, $this->vendors))->register();
        }

        add_action('rest_api_init', function (): void {
            (new RulesController($this->rules))->register();
        });

        // After logging in, vendors land on their panel instead of wp-admin.
        add_filter('login_redirect', static function (string $redirect, string $requested, $user): string {
            if ($user instanceof \WP_User && in_array(Installer::VENDOR_ROLE, (array) $user->roles, true) && !user_can($user, 'edit_posts') && ($requested === '' || str_contains($requested, '/wp-admin'))) {
                return Routes::panelUrl();
            }
            return $redirect;
        }, 10, 3);
    }
}
