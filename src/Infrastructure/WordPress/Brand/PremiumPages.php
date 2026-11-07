<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Brand;

use Cheppi\Sellers\Application\BusinessRulesRepository;
use Cheppi\Sellers\Application\Ledger;
use Cheppi\Sellers\Domain\Ledger\LedgerEntry;
use Cheppi\Sellers\Domain\Ledger\LedgerEntryType;
use Cheppi\Sellers\Domain\Vendor\Vendor;
use Cheppi\Sellers\Infrastructure\WordPress\Admin\VendorsPage;
use Cheppi\Sellers\Infrastructure\WordPress\Capabilities;
use Cheppi\Sellers\Infrastructure\WordPress\Front\Routes;
use Cheppi\Sellers\Infrastructure\WordPress\Persistence\WpVendorExtras;
use Cheppi\Sellers\Infrastructure\WordPress\Persistence\WpVendorRepository;
use DateTimeImmutable;

/**
 * Premium brand pages: Cheppi designs a normal WordPress page in Divi 5 and links it to the vendor.
 * It is served at /marca/<store>/ (the URL stays; WordPress renders the page as usual), and the
 * design fee is charged to the vendor's balance.
 */
final class PremiumPages
{
    public const ACTION = 'cheppi_premium_save';

    public function __construct(
        private readonly WpVendorRepository $vendors,
        private readonly WpVendorExtras $extras,
        private readonly Ledger $ledger,
        private readonly BusinessRulesRepository $rules
    ) {
    }

    public function register(): void
    {
        add_filter('request', [$this, 'route']);
        add_filter('redirect_canonical', static fn ($redirect) => get_query_var(Routes::BRAND_VAR) !== '' ? false : $redirect);
        add_action('cheppi_sellers_vendor_detail', [$this, 'adminCard']);
        add_filter('cheppi_sellers_brand_url', fn (string $url, Vendor $vendor): string => $this->urlFor($vendor), 10, 2);
        add_action('admin_post_' . self::ACTION, [$this, 'save']);
        // The page itself is reachable only through /marca/<store>/.
        add_filter('wp_robots', function (array $robots): array {
            if (is_page() && $this->extras->vendorForPremiumPage((int) get_queried_object_id()) > 0 && get_query_var(Routes::BRAND_VAR) === '') {
                $robots['noindex'] = true;
            }
            return $robots;
        });
    }

    /** @param array<string, mixed> $vars */
    public function route(array $vars): array
    {
        if (empty($vars[Routes::BRAND_VAR])) {
            return $vars;
        }
        $vendor = $this->vendors->findBySlug(sanitize_title((string) $vars[Routes::BRAND_VAR]));
        $premium = $vendor !== null && $vendor->isPublic() ? $this->extras->premium((int) $vendor->id) : null;
        if ($premium === null || $premium['status'] !== WpVendorExtras::PREMIUM_ACTIVE || $premium['page_id'] === 0 || get_post_status($premium['page_id']) !== 'publish') {
            return ['error' => '404'];
        }
        return ['page_id' => $premium['page_id'], Routes::BRAND_VAR => $vars[Routes::BRAND_VAR]];
    }

    /** Public URL of a vendor's active premium page, or ''. */
    public function urlFor(Vendor $vendor): string
    {
        $p = $this->extras->premium((int) $vendor->id);
        return $p['status'] === WpVendorExtras::PREMIUM_ACTIVE && $p['page_id'] > 0 ? Routes::brandUrl($vendor->storeSlug) : '';
    }

    public function adminCard(Vendor $vendor): void
    {
        $premium = $this->extras->premium((int) $vendor->id);
        $service = $this->rules->current()->brandPage;
        ?>
        <section class="ch-card">
            <h2 class="ch-card__title"><?php esc_html_e('Página de marca premium', 'cheppi-sellers'); ?></h2>
            <?php if ($premium['status'] === WpVendorExtras::PREMIUM_REQUESTED) : ?>
                <div class="ch-alert ch-alert--warning ch-mb"><p><?php esc_html_e('El vendedor solicitó este servicio.', 'cheppi-sellers'); ?></p></div>
            <?php endif; ?>
            <p class="ch-muted"><?php esc_html_e('Diseña una página en Divi 5 (Páginas → Añadir), publícala y elígela aquí. Se verá en', 'cheppi-sellers'); ?> <code><?php echo esc_html(Routes::brandUrl($vendor->storeSlug)); ?></code></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ch-grid">
                <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION); ?>">
                <input type="hidden" name="vendor_id" value="<?php echo (int) $vendor->id; ?>">
                <?php wp_nonce_field(self::ACTION . $vendor->id); ?>
                <label class="ch-field"><span class="ch-field__label"><?php esc_html_e('Página', 'cheppi-sellers'); ?></span>
                    <?php wp_dropdown_pages(['name' => 'page_id', 'selected' => $premium['page_id'], 'show_option_none' => __('— Sin página —', 'cheppi-sellers'), 'option_none_value' => '0', 'class' => 'ch-input']); ?>
                </label>
                <label class="ch-check"><input type="checkbox" name="charge" value="1"> <span><?php echo esc_html(sprintf(__('Cobrar el diseño (S/ %s) del saldo del vendedor', 'cheppi-sellers'), $service->designFee->toDecimal())); ?></span></label>
                <div><button class="ch-btn ch-btn--secondary" type="submit"><?php esc_html_e('Guardar página premium', 'cheppi-sellers'); ?></button>
                <?php if ($premium['status'] === WpVendorExtras::PREMIUM_ACTIVE) : ?> <a class="ch-btn ch-btn--ghost" href="<?php echo esc_url(Routes::brandUrl($vendor->storeSlug)); ?>" target="_blank"><?php esc_html_e('Ver', 'cheppi-sellers'); ?></a><?php endif; ?></div>
            </form>
        </section>
        <?php
    }

    public function save(): void
    {
        $vendorId = absint($_POST['vendor_id'] ?? 0);
        if (!current_user_can(Capabilities::MANAGE) || !check_admin_referer(self::ACTION . $vendorId)) {
            wp_die(esc_html__('No tienes permiso para hacer esto.', 'cheppi-sellers'), 403);
        }
        $pageId = absint($_POST['page_id'] ?? 0);
        $pageId = $pageId > 0 && get_post_type($pageId) === 'page' ? $pageId : 0;
        $current = $this->extras->premium($vendorId);
        $status = $pageId > 0 ? WpVendorExtras::PREMIUM_ACTIVE : ($current['status'] === WpVendorExtras::PREMIUM_REQUESTED ? WpVendorExtras::PREMIUM_REQUESTED : '');
        $this->extras->savePremium($vendorId, $pageId, $status);

        if (!empty($_POST['charge'])) {
            $now = new DateTimeImmutable('now');
            $fee = $this->rules->current()->brandPage->designFee;
            $this->ledger->add([new LedgerEntry($vendorId, LedgerEntryType::ServiceFee, $fee->negate(), $now, $now, null, null, null, 'Diseño de página de marca premium')]);
        }
        update_option('cheppi_sellers_flush_rewrites', '1', true);
        wp_safe_redirect(add_query_arg('premium', '1', VendorsPage::detailUrl($vendorId)));
        exit;
    }
}
