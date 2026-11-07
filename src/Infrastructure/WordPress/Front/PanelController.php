<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Front;

use Cheppi\Sellers\Application\BusinessRulesRepository;
use Cheppi\Sellers\Application\DeliveryZones;
use Cheppi\Sellers\Application\DocumentStore;
use Cheppi\Sellers\Application\Fulfillments;
use Cheppi\Sellers\Application\Ledger;
use Cheppi\Sellers\Application\VendorRepository;
use Cheppi\Sellers\Domain\Catalog\ProductStatus;
use Cheppi\Sellers\Domain\Order\FulfillmentStatus;
use Cheppi\Sellers\Domain\Vendor\Vendor;
use Cheppi\Sellers\Infrastructure\WordPress\Assets;
use Cheppi\Sellers\Infrastructure\WordPress\Catalog\VendorStats;
use Cheppi\Sellers\Infrastructure\WordPress\Catalog\WcProductCatalog;
use Cheppi\Sellers\Infrastructure\WordPress\Orders\OrderSync;
use DateTimeImmutable;
use WP_User;

/**
 * Renders the seller panel as a standalone app page (no theme, no Divi), so it stays fast and consistent.
 */
final class PanelController
{
    private const PER_PAGE = 20;

    public function __construct(
        private readonly VendorRepository $vendors,
        private readonly DocumentStore $documents,
        private readonly BusinessRulesRepository $rules,
        private readonly WcProductCatalog $catalog,
        private readonly VendorStats $stats,
        private readonly Fulfillments $fulfillments,
        private readonly Ledger $ledger,
        private readonly DeliveryZones $zones,
        private readonly Assets $assets,
        private readonly \Cheppi\Sellers\Application\Payouts $payouts,
        private readonly \Cheppi\Sellers\Infrastructure\WordPress\Persistence\WpVendorExtras $extras,
        private readonly \Cheppi\Sellers\Infrastructure\WordPress\Promotions\Campaigns $campaigns,
        private readonly \Cheppi\Sellers\Infrastructure\WordPress\Promotions\VendorCoupons $coupons,
        private readonly \Cheppi\Sellers\Infrastructure\WordPress\Promotions\FeaturedProducts $featured
    ) {
    }

    public function register(): void
    {
        add_action('template_redirect', [$this, 'route'], 1);
    }

    public function route(): void
    {
        $screen = (string) get_query_var(Routes::QUERY_VAR);
        if ($screen === '') {
            return;
        }
        self::sendNoCacheHeaders();

        $user = wp_get_current_user();
        $vendor = $user->exists() ? $this->vendors->findByUserId($user->ID) : null;

        if ($screen === 'registro') {
            if ($vendor !== null) {
                $this->redirect(Routes::panelUrl());
            }
            $this->renderRegister([], []);
            exit;
        }
        if (!$user->exists()) {
            $this->renderPage('guest', __('Tu panel de vendedor', 'cheppi-sellers'), ['user' => $user], null);
        }
        if ($vendor === null) {
            $this->renderPage('no-store', __('Tu panel de vendedor', 'cheppi-sellers'), ['user' => $user], null);
        }
        if ($screen !== 'inicio' && !$vendor->isPublic()) {
            $this->redirect(Routes::panelUrl());
        }

        $base = ['user' => $user, 'vendor' => $vendor, 'flash' => Flash::take()];
        match ($screen) {
            'productos' => $this->renderProducts($base),
            'producto' => $this->renderProductForm($base, (int) get_query_var(Routes::ID_VAR)),
            'mi-tienda' => $this->renderPage('storefront', __('Mi tienda', 'cheppi-sellers'), $base + [
                'premium' => $this->extras->premium((int) $vendor->id),
                'brandPage' => $this->rules->current()->brandPage,
            ], 'mi-tienda'),
            'pagos' => $this->renderPage('payments', __('Pagos', 'cheppi-sellers'), $base + [
                'balance' => $this->ledger->balance((int) $vendor->id, new DateTimeImmutable('now')),
                'bank' => $this->extras->bank((int) $vendor->id),
                'payouts' => $this->payouts->list(null, (int) $vendor->id, 24, 0),
                'movements' => $this->ledger->forVendor((int) $vendor->id, 40),
                'nextCutoff' => (new \Cheppi\Sellers\Domain\Payout\PayoutSchedule($this->rules->current()))->nextCutoff(new DateTimeImmutable('now', wp_timezone())),
                'rules' => $this->rules->current(),
            ], 'pagos'),
            'promociones' => $this->renderPromotions($base),
            'metricas' => $this->renderMetrics($base),
            'importar' => $this->renderPage('import', __('Importar productos', 'cheppi-sellers'), $base, 'productos'),
            'pedidos' => $this->renderOrders($base),
            'pedido' => $this->renderOrder($base, (int) get_query_var(Routes::ID_VAR)),
            'envios' => $this->renderPage('shipping', __('Envíos', 'cheppi-sellers'), $base + ['zones' => $this->zones->forVendor((int) $vendor->id)], 'envios'),
            default => $this->renderHome($base),
        };
    }

    /** @param array<string, mixed> $base */
    private function renderHome(array $base): never
    {
        /** @var Vendor $vendor */
        $vendor = $base['vendor'];
        $this->renderPage('home', __('Inicio', 'cheppi-sellers'), $base + [
            'documents' => $this->documents->forVendor((int) $vendor->id),
            'rules' => $this->rules->current(),
            'notice' => isset($_GET['enviado']) ? sanitize_key((string) $_GET['enviado']) : '',
            'byStatus' => $vendor->isPublic() ? $this->stats->productsByStatus($vendor->userId) : [],
            'byCategory' => $vendor->isPublic() ? $this->stats->productsByCategory($vendor->userId) : [],
            'lowStock' => $vendor->isPublic() ? $this->stats->lowStock($vendor->userId) : 0,
            'salesByDay' => $this->stats->salesByDay((int) $vendor->id),
            'topProducts' => $this->stats->topProducts((int) $vendor->id),
            'balance' => $this->ledger->balance((int) $vendor->id, new DateTimeImmutable('now')),
            'openOrders' => array_sum(array_intersect_key($this->fulfillments->countsForVendor((int) $vendor->id), array_flip(['new', 'preparing', 'shipped']))),
            'hasZones' => $this->zones->forVendor((int) $vendor->id) !== [],
        ], 'inicio');
    }

    /** @param array<string, mixed> $base */
    private function renderProducts(array $base): never
    {
        /** @var Vendor $vendor */
        $vendor = $base['vendor'];
        $status = ProductStatus::tryFrom(sanitize_key((string) ($_GET['estado'] ?? '')));
        $search = sanitize_text_field(wp_unslash((string) ($_GET['buscar'] ?? '')));
        $page = max(1, absint($_GET['pagina'] ?? 1));
        $layout = self::productsLayout();
        $result = $this->catalog->listForVendor($vendor->userId, $status, $search, $page, self::PER_PAGE);
        $rules = $this->rules->current();

        $this->renderPage('products', __('Productos', 'cheppi-sellers'), $base + [
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'pages' => (int) ceil($result['total'] / self::PER_PAGE),
            'status' => $status,
            'search' => $search,
            'counts' => $this->stats->productsByStatus($vendor->userId),
            'limit' => $rules->plan($vendor->planId)?->productLimit,
            'used' => $this->catalog->countForVendor($vendor->userId),
            'layout' => $layout,
        ], 'productos');
    }

    /** @param array<string, mixed> $base */
    private function renderPromotions(array $base): never
    {
        /** @var Vendor $vendor */
        $vendor = $base['vendor'];
        $rules = $this->rules->current();
        $now = new DateTimeImmutable('now');
        $this->renderPage('promotions', __('Promociones', 'cheppi-sellers'), $base + [
            'campaigns' => $this->campaigns->open($now),
            'joined' => $this->extras->campaigns((int) $vendor->id),
            'normalBps' => $vendor->commissionOverrideBps ?? ($rules->plan($vendor->planId)?->commissionBps ?? $rules->defaultCommissionBps),
            'coupons' => $this->coupons->forVendor($vendor),
            'featured' => $this->featured->forVendor($vendor),
            'publishable' => wc_get_products(['author' => $vendor->userId, 'status' => 'publish', 'limit' => 100, 'orderby' => 'title', 'order' => 'ASC']),
            'featuredPrice' => $rules->featuredPrice(),
        ], 'promociones');
    }

    /** @param array<string, mixed> $base */
    private function renderMetrics(array $base): never
    {
        /** @var Vendor $vendor */
        $vendor = $base['vendor'];
        $days = (int) ($_GET['dias'] ?? 30);
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;
        $rules = $this->rules->current();
        $this->renderPage('metrics', __('Métricas', 'cheppi-sellers'), $base + [
            'days' => $days,
            'm' => $this->stats->metrics((int) $vendor->id, $days, $rules->dispatchHours),
            'salesByDay' => $this->stats->salesByDay((int) $vendor->id, $days),
            'topProducts' => $this->stats->topProducts((int) $vendor->id, 8),
            'rules' => $rules,
        ], 'metricas');
    }

    /** @param array<string, mixed> $base */
    private function renderOrders(array $base): never
    {
        /** @var Vendor $vendor */
        $vendor = $base['vendor'];
        $status = FulfillmentStatus::tryFrom(sanitize_key((string) ($_GET['estado'] ?? '')));
        $page = max(1, absint($_GET['pagina'] ?? 1));
        $counts = $this->fulfillments->countsForVendor((int) $vendor->id);
        $parts = $this->fulfillments->forVendor((int) $vendor->id, $status, self::PER_PAGE, ($page - 1) * self::PER_PAGE);
        $total = $status === null ? array_sum($counts) : ($counts[$status->value] ?? 0);

        $this->renderPage('orders', __('Pedidos', 'cheppi-sellers'), $base + [
            'parts' => $parts,
            'orders' => array_filter(array_combine(
                array_map(static fn ($p): int => $p->orderId, $parts),
                array_map(static fn ($p) => wc_get_order($p->orderId), $parts)
            ) ?: []),
            'status' => $status,
            'counts' => $counts,
            'page' => $page,
            'pages' => (int) ceil($total / self::PER_PAGE),
        ], 'pedidos');
    }

    /** @param array<string, mixed> $base */
    private function renderOrder(array $base, int $orderId): never
    {
        /** @var Vendor $vendor */
        $vendor = $base['vendor'];
        $part = $this->fulfillments->find($orderId, (int) $vendor->id);
        $order = $part !== null ? wc_get_order($orderId) : null;
        if ($part === null || !$order instanceof \WC_Order) {
            Flash::set(['type' => 'error', 'message' => __('Pedido no encontrado.', 'cheppi-sellers')]);
            $this->redirect(Routes::ordersUrl());
        }
        $items = array_filter(
            $order->get_items('line_item'),
            static fn ($item): bool => (int) $item->get_meta(OrderSync::META_VENDOR) === (int) $vendor->id
                || ((int) $item->get_meta(OrderSync::META_VENDOR) === 0 && (int) get_post_field('post_author', $item->get_product_id()) === $vendor->userId)
        );
        $entries = array_filter($this->ledger->forOrder($orderId), static fn ($e): bool => $e->vendorId === (int) $vendor->id);

        $this->renderPage('order', sprintf(__('Pedido #%d', 'cheppi-sellers'), $orderId), $base + [
            'part' => $part,
            'order' => $order,
            'items' => $items,
            'entries' => array_values($entries),
        ], 'pedidos');
    }

    /** @param array<string, mixed> $base */
    private function renderProductForm(array $base, int $productId): never
    {
        /** @var Vendor $vendor */
        $vendor = $base['vendor'];
        $flash = $base['flash'];
        $stored = $productId > 0 ? $this->catalog->formValues($vendor->userId, $productId) : null;
        if ($productId > 0 && $stored === null) {
            Flash::set(['type' => 'error', 'message' => __('Producto no encontrado.', 'cheppi-sellers')]);
            $this->redirect(Routes::productsUrl());
        }
        $values = is_array($flash['values'] ?? null) ? $flash['values'] + ($stored ?? []) : ($stored ?? ['type' => 'simple', 'variations' => []]);

        $this->renderPage('product-form', $productId > 0 ? __('Editar producto', 'cheppi-sellers') : __('Nuevo producto', 'cheppi-sellers'), $base + [
            'productId' => $productId,
            'values' => $values,
            'images' => $stored['images'] ?? [],
            'errors' => is_array($flash['errors'] ?? null) ? $flash['errors'] : [],
            'categories' => $this->catalog->categories(),
            'moderated' => $this->rules->current()->moderateNewProducts,
        ], 'productos');
    }

    /** List or grid; the choice is kept in a cookie so the page renders right without JavaScript. */
    private static function productsLayout(): string
    {
        $asked = sanitize_key((string) ($_GET['vista'] ?? ''));
        if (in_array($asked, ['lista', 'grid'], true)) {
            setcookie('cheppi_products_view', $asked, ['expires' => time() + YEAR_IN_SECONDS, 'path' => '/' . Routes::BASE . '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax']);
            return $asked;
        }
        return ($_COOKIE['cheppi_products_view'] ?? '') === 'grid' ? 'grid' : 'lista';
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, mixed>  $values
     */
    public function renderRegister(array $errors, array $values): void
    {
        self::sendNoCacheHeaders();
        $this->renderPage('register', __('Vende en Cheppi', 'cheppi-sellers'), [
            'user' => wp_get_current_user(),
            'errors' => $errors,
            'values' => $values,
        ], null, false);
    }

    /**
     * @param array<string, mixed> $vars
     * @param string|null $nav current menu item; null renders without the sidebar shell
     */
    private function renderPage(string $view, string $title, array $vars, ?string $nav, bool $exit = true): void
    {
        status_header(200);
        $vars += ['title' => $title, 'view' => $view, 'nav' => $nav, 'assets' => $this->assets, 'vendor' => null];
        $vars['withShell'] = $nav !== null;
        $vars['content'] = View::capture('panel/' . $view, $vars);
        echo View::capture('panel/layout', $vars); // phpcs:ignore WordPress.Security.EscapeOutput -- templates escape their own output
        if ($exit) {
            exit;
        }
    }

    private function redirect(string $url): never
    {
        wp_safe_redirect($url);
        exit;
    }

    /** Panel pages are personal and carry nonces: never cache them (WordPress, LiteSpeed, browsers, proxies). */
    public static function sendNoCacheHeaders(): void
    {
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        nocache_headers();
        header('X-LiteSpeed-Cache-Control: no-cache');
        header('X-Robots-Tag: noindex, nofollow');
    }

    public static function initials(WP_User $user, ?Vendor $vendor = null): string
    {
        $name = $vendor?->storeName ?: ($user->display_name ?: $user->user_email);
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $letters = mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[1] ?? '', 0, 1);
        return mb_strtoupper($letters !== '' ? $letters : 'C');
    }
}
