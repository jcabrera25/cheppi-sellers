<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Front;

use Cheppi\Sellers\Domain\Vendor\Vendor;
use Cheppi\Sellers\Domain\Vendor\VendorStatus;
use Cheppi\Sellers\Infrastructure\WordPress\Assets;
use Cheppi\Sellers\Infrastructure\WordPress\Persistence\WpVendorRepository;
use WP_Query;

/**
 * Public pages on the "Tiendas" WordPress page:
 *   /tienda/          directory of approved stores
 *   /tienda/<slug>/   one store with its products
 * The theme renders header and footer (templates/store/page.php), so Divi's Theme Builder works as on any page.
 */
final class StoreController
{
    private const PER_PAGE = 24;

    /** @var array{view: string, vars: array<string, mixed>}|null */
    private static ?array $screen = null;

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
        $pageId = StorePage::id();
        $slug = sanitize_title((string) get_query_var(Routes::STORE_VAR));
        if (!($pageId > 0 && is_page($pageId)) && $slug === '') {
            return;
        }

        if ($slug === '') {
            $this->directory();
        } else {
            $vendor = $this->vendors->findBySlug($slug);
            if ($vendor === null || !$vendor->isPublic()) {
                global $wp_query;
                $wp_query->set_404();
                status_header(404);
                return;
            }
            $this->store($vendor);
        }

        $this->assets->enqueue('store');
        remove_action('wp_head', 'rel_canonical');
        add_filter('body_class', static fn (array $c): array => array_merge(array_diff($c, ['et_right_sidebar']), ['et_full_width_page', 'woocommerce', 'cheppi-store-page']), 20);
        add_filter('template_include', static fn (): string => CHEPPI_SELLERS_DIR . '/templates/store/page.php', 50);
    }

    private function store(Vendor $vendor): void
    {
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
        self::$screen = ['view' => 'store/store', 'vars' => [
            'vendor' => $vendor,
            'products' => array_values(array_filter(array_map('wc_get_product', $query->posts))),
            'total' => (int) $query->found_posts,
            'page' => $page,
            'pages' => (int) $query->max_num_pages,
        ]];
        $this->head(
            $vendor->storeName,
            $vendor->about !== '' ? wp_trim_words($vendor->about, 30, '…') : '',
            Routes::storeUrl($vendor->storeSlug)
        );
    }

    private function directory(): void
    {
        self::$screen = ['view' => 'store/directory', 'vars' => [
            'vendors' => $this->vendors->list(VendorStatus::Approved, 96, 0),
        ]];
        $this->head(__('Tiendas', 'cheppi-sellers'), __('Marcas y emprendedores que venden en Cheppi Market.', 'cheppi-sellers'), home_url('/' . Routes::STORE_BASE . '/'));
    }

    private function head(string $title, string $description, string $canonical): void
    {
        $full = $title . ' · ' . get_bloginfo('name');
        // Themes and SEO plugins build the title in different ways: answer the earliest hook.
        add_filter('pre_get_document_title', static fn (): string => $full, 99);
        add_action('wp_head', static function () use ($description, $canonical): void {
            if ($description !== '') {
                printf('<meta name="description" content="%s">' . "\n", esc_attr($description));
            }
            printf('<link rel="canonical" href="%s">' . "\n", esc_url($canonical));
        }, 1);
    }

    /** Called from templates/store/page.php between the theme header and footer. */
    public static function renderCurrent(): void
    {
        if (self::$screen !== null) {
            echo View::capture(self::$screen['view'], self::$screen['vars']); // phpcs:ignore WordPress.Security.EscapeOutput -- templates escape
        }
    }
}
