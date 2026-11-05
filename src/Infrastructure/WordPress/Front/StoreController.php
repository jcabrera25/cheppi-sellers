<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Front;

use Cheppi\Sellers\Domain\Vendor\Vendor;
use Cheppi\Sellers\Infrastructure\WordPress\Assets;
use Cheppi\Sellers\Infrastructure\WordPress\Persistence\WpVendorRepository;
use WP_Query;

/**
 * Public store page /tienda/<slug>/ inside the site theme (header, footer, cart keep working).
 * Only approved vendors are visible; anything else is a 404.
 */
final class StoreController
{
    private const PER_PAGE = 24;

    private ?Vendor $vendor = null;

    public function __construct(
        private readonly WpVendorRepository $vendors,
        private readonly Assets $assets
    ) {
    }

    public function register(): void
    {
        add_action('template_redirect', [$this, 'route'], 2);
    }

    public function route(): void
    {
        $slug = (string) get_query_var(Routes::STORE_VAR);
        if ($slug === '') {
            return;
        }
        $vendor = $this->vendors->findBySlug(sanitize_title($slug));
        if ($vendor === null || !$vendor->isPublic()) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            return;
        }
        $this->vendor = $vendor;

        // Themes and SEO plugins build the title in different ways: answer the earliest hook.
        $title = $vendor->storeName . ' · ' . get_bloginfo('name');
        add_filter('pre_get_document_title', static fn (): string => $title, 99);
        add_action('wp_head', function (): void {
            if ($this->vendor !== null && $this->vendor->about !== '') {
                printf('<meta name="description" content="%s">' . "\n", esc_attr(wp_trim_words($this->vendor->about, 30, '…')));
            }
            printf('<link rel="canonical" href="%s">' . "\n", esc_url(Routes::storeUrl((string) $this->vendor?->storeSlug)));
        }, 1);
        add_filter('body_class', static fn (array $c): array => array_merge($c, ['woocommerce', 'cheppi-store-page']));

        $this->assets->enqueue('store');
        $page = max(1, absint($_GET['pagina'] ?? 1));
        $query = new WP_Query([
            'post_type' => 'product',
            'post_status' => 'publish',
            'author' => $vendor->userId,
            'posts_per_page' => self::PER_PAGE,
            'paged' => $page,
            'orderby' => 'date',
            'order' => 'DESC',
            'ignore_sticky_posts' => true,
            'tax_query' => [[
                'taxonomy' => 'product_visibility',
                'field' => 'name',
                'terms' => ['exclude-from-catalog'],
                'operator' => 'NOT IN',
            ]],
        ]);

        status_header(200);
        get_header();
        echo View::capture('store/store', [ // phpcs:ignore WordPress.Security.EscapeOutput -- template escapes
            'vendor' => $vendor,
            'products' => array_values(array_filter(array_map('wc_get_product', $query->posts))),
            'total' => (int) $query->found_posts,
            'page' => $page,
            'pages' => (int) $query->max_num_pages,
        ]);
        get_footer();
        exit;
    }
}
