<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Front;

use Cheppi\Sellers\Application\BusinessRulesRepository;
use Cheppi\Sellers\Application\Fulfillments;
use Cheppi\Sellers\Application\Ledger;
use Cheppi\Sellers\Domain\Order\FulfillmentStatus;
use Cheppi\Sellers\Domain\Payout\PayoutStatus;
use Cheppi\Sellers\Domain\Vendor\Vendor;
use Cheppi\Sellers\Infrastructure\WordPress\Admin\BusinessRulesPage;
use Cheppi\Sellers\Infrastructure\WordPress\Admin\CampaignsPage;
use Cheppi\Sellers\Infrastructure\WordPress\Admin\MetricsPage;
use Cheppi\Sellers\Infrastructure\WordPress\Admin\PayoutsPage;
use Cheppi\Sellers\Infrastructure\WordPress\Admin\VendorsPage;
use Cheppi\Sellers\Infrastructure\WordPress\Assets;
use Cheppi\Sellers\Infrastructure\WordPress\Capabilities;
use Cheppi\Sellers\Infrastructure\WordPress\Catalog\WcProductCatalog;
use Cheppi\Sellers\Infrastructure\WordPress\Persistence\WpPayouts;
use Cheppi\Sellers\Infrastructure\WordPress\Persistence\WpVendorRepository;
use WP_Query;

/**
 * Cheppi's management panel at /gestion/: the same look as the seller panel, for the marketplace team.
 * Stores, every product (approve, send back, edit with the full editor), every order (move any vendor's
 * part), payouts, campaigns, business rules and metrics — without entering wp-admin.
 * Screens that also exist in wp-admin reuse those pages, so there is one implementation of each.
 */
final class ManageController
{
    private const PER_PAGE = 20;

    public function __construct(
        private readonly WpVendorRepository $vendors,
        private readonly WcProductCatalog $catalog,
        private readonly Fulfillments $fulfillments,
        private readonly Ledger $ledger,
        private readonly WpPayouts $payouts,
        private readonly BusinessRulesRepository $rules,
        private readonly Assets $assets,
        private readonly VendorsPage $vendorsPage,
        private readonly PayoutsPage $payoutsPage,
        private readonly CampaignsPage $campaignsPage,
        private readonly BusinessRulesPage $rulesPage,
        private readonly MetricsPage $metricsPage
    ) {
    }

    public function register(): void
    {
        add_action('template_redirect', [$this, 'route'], 1);
    }

    public static function url(): string
    {
        return Routes::manageUrl();
    }

    public function route(): void
    {
        $section = (string) get_query_var(Routes::MANAGE_VAR);
        if ($section === '') {
            return;
        }
        PanelController::sendNoCacheHeaders();
        if (!is_user_logged_in()) {
            wp_safe_redirect(wp_login_url(home_url((string) ($_SERVER['REQUEST_URI'] ?? '/'))));
            exit;
        }
        if (!current_user_can(Capabilities::MANAGE)) {
            status_header(403);
            wp_die(esc_html__('Esta sección es solo para el equipo de Cheppi.', 'cheppi-sellers'), 403);
        }
        $id = (string) get_query_var(Routes::ID_VAR);

        match ($section) {
            'tiendas' => $this->stores($id),
            'productos' => $this->products($id),
            'pedidos' => $id !== '' ? $this->order((int) $id) : $this->orders(),
            'liquidaciones' => $this->embed('liquidaciones', __('Liquidaciones', 'cheppi-sellers'), fn () => $this->payoutsPage->render()),
            'campanas' => $this->embed('campanas', __('Campañas', 'cheppi-sellers'), fn () => $this->campaignsPage->render()),
            'reglas' => $this->embed('reglas', __('Reglas del negocio', 'cheppi-sellers'), fn () => $this->rulesPage->render()),
            default => $this->home(),
        };
    }

    private function home(): never
    {
        $counts = $this->vendors->countByStatus();
        $orders = $this->fulfillments->countsAll();
        $totals = $this->payouts->totals();
        $pending = (new WP_Query(['post_type' => 'product', 'post_status' => 'pending', 'posts_per_page' => 1, 'fields' => 'ids']))->found_posts;
        $cards = [
            [__('Solicitudes por revisar', 'cheppi-sellers'), (string) (($counts['pending'] ?? 0) + ($counts['needs_info'] ?? 0)), Routes::manageUrl('tiendas') . '?estado=pending'],
            [__('Productos en revisión', 'cheppi-sellers'), (string) $pending, Routes::manageUrl('productos') . '?estado=pending'],
            [__('Pedidos nuevos', 'cheppi-sellers'), (string) (($orders['new'] ?? 0) + ($orders['preparing'] ?? 0)), Routes::manageUrl('pedidos') . '?estado=new'],
            [__('Por transferir', 'cheppi-sellers'), 'S/ ' . number_format(($totals['pending']['cents'] ?? 0) / 100, 2), Routes::manageUrl('liquidaciones')],
        ];
        $this->embed('inicio', __('Gestión de Cheppi', 'cheppi-sellers'), function () use ($cards): void {
            echo View::capture('manage/home', ['cards' => $cards]); // phpcs:ignore WordPress.Security.EscapeOutput
            $this->metricsPage->render();
        });
    }

    private function stores(string $id): never
    {
        // VendorsPage chooses list / detail / new from the query string, as in wp-admin.
        if ($id === 'nueva') {
            $_GET['nuevo'] = '1';
        } elseif ($id !== '') {
            $_GET['vendor'] = $id;
        }
        $this->embed('tiendas', __('Tiendas', 'cheppi-sellers'), fn () => $this->vendorsPage->render());
    }

    private function products(string $id): never
    {
        if ($id !== '') {
            $this->productForm($id === 'nuevo' ? 0 : (int) $id);
        }
        $status = sanitize_key((string) ($_GET['estado'] ?? ''));
        $vendorId = absint($_GET['tienda'] ?? 0);
        $search = sanitize_text_field(wp_unslash((string) ($_GET['buscar'] ?? '')));
        $page = max(1, absint($_GET['pagina'] ?? 1));
        $vendor = $vendorId > 0 ? $this->vendors->find($vendorId) : null;
        $query = new WP_Query([
            'post_type' => 'product',
            'post_status' => in_array($status, ['pending', 'publish', 'draft'], true) ? [$status] : ['pending', 'publish', 'draft', 'private'],
            'author' => $vendor?->userId ?? '',
            's' => $search,
            'orderby' => 'modified',
            'order' => 'DESC',
            'posts_per_page' => self::PER_PAGE,
            'paged' => $page,
            'ignore_sticky_posts' => true,
        ]);
        $products = array_values(array_filter(array_map('wc_get_product', $query->posts)));
        $owners = $this->vendors->findByUserIds(array_map(static fn ($p): int => (int) get_post_field('post_author', $p->get_id()), $products));
        global $wpdb;
        $statusCounts = [];
        foreach ((array) $wpdb->get_results("SELECT post_status, COUNT(*) AS n FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status IN ('pending','publish','draft','private') GROUP BY post_status", ARRAY_A) as $row) {
            $statusCounts[(string) $row['post_status']] = (int) $row['n'];
        }
        $this->embed('productos', __('Productos', 'cheppi-sellers'), static function () use ($products, $owners, $status, $vendor, $search, $page, $query, $statusCounts): void {
            echo View::capture('manage/products', [ // phpcs:ignore WordPress.Security.EscapeOutput
                'products' => $products,
                'owners' => $owners,
                'status' => $status,
                'vendor' => $vendor,
                'search' => $search,
                'page' => $page,
                'pages' => (int) $query->max_num_pages,
                'counts' => $statusCounts,
            ]);
        });
    }

    private function productForm(int $productId): never
    {
        $ownerUserId = $productId > 0 ? (int) get_post_field('post_author', $productId) : 0;
        $vendor = $productId > 0
            ? $this->vendors->findByUserId($ownerUserId)
            : $this->vendors->find(absint($_GET['tienda'] ?? 0));
        $flash = Flash::take();

        if ($vendor === null) {
            // A product without store (Cheppi's own) or a new product without a chosen store.
            $this->embed('productos', $productId > 0 ? __('Editar producto', 'cheppi-sellers') : __('Nuevo producto', 'cheppi-sellers'), function () use ($productId): void {
                echo View::capture('manage/product-owner', [ // phpcs:ignore WordPress.Security.EscapeOutput
                    'productId' => $productId,
                    'vendors' => $this->vendors->list(\Cheppi\Sellers\Domain\Vendor\VendorStatus::Approved, 200, 0),
                ]);
            });
        }
        $stored = $productId > 0 ? $this->catalog->formValues($vendor->userId, $productId) : null;
        $vars = PanelController::productFormVars($this->catalog, $vendor->userId, $productId, $stored, $flash, false) + [
            'backUrl' => Routes::manageUrl('productos'),
            'ownerId' => $vendor->userId,
            'ownerName' => $vendor->storeName,
            'vendor' => $vendor,
        ];
        $this->embed('productos', $productId > 0 ? __('Editar producto', 'cheppi-sellers') : __('Nuevo producto', 'cheppi-sellers'), static function () use ($vars): void {
            echo View::capture('panel/product-form', $vars); // phpcs:ignore WordPress.Security.EscapeOutput
        }, $flash);
    }

    private function orders(): never
    {
        $status = FulfillmentStatus::tryFrom(sanitize_key((string) ($_GET['estado'] ?? '')));
        $page = max(1, absint($_GET['pagina'] ?? 1));
        $parts = $this->fulfillments->listAll($status, self::PER_PAGE, ($page - 1) * self::PER_PAGE);
        $counts = $this->fulfillments->countsAll();
        $vendors = [];
        foreach (array_unique(array_map(static fn ($p): int => $p->vendorId, $parts)) as $vid) {
            $vendors[$vid] = $this->vendors->find($vid);
        }
        $orders = [];
        foreach ($parts as $p) {
            $orders[$p->orderId] ??= wc_get_order($p->orderId) ?: null;
        }
        $total = $status === null ? array_sum($counts) : ($counts[$status->value] ?? 0);
        $this->embed('pedidos', __('Pedidos', 'cheppi-sellers'), static function () use ($parts, $counts, $vendors, $orders, $status, $page, $total): void {
            echo View::capture('manage/orders', [ // phpcs:ignore WordPress.Security.EscapeOutput
                'parts' => $parts,
                'counts' => $counts,
                'vendors' => $vendors,
                'orders' => array_filter($orders),
                'status' => $status,
                'page' => $page,
                'pages' => (int) ceil($total / self::PER_PAGE),
            ]);
        });
    }

    private function order(int $orderId): never
    {
        $order = wc_get_order($orderId);
        $parts = $order ? $this->fulfillments->forOrder($orderId) : [];
        if (!$order instanceof \WC_Order) {
            Flash::set(['type' => 'error', 'message' => __('Pedido no encontrado.', 'cheppi-sellers')]);
            wp_safe_redirect(Routes::manageUrl('pedidos'));
            exit;
        }
        $entries = $this->ledger->forOrder($orderId);
        $sections = [];
        foreach ($parts as $part) {
            $vendor = $this->vendors->find($part->vendorId);
            if (!$vendor instanceof Vendor) {
                continue;
            }
            $items = array_filter(
                $order->get_items('line_item'),
                static fn ($item): bool => (int) $item->get_meta(\Cheppi\Sellers\Infrastructure\WordPress\Orders\OrderSync::META_VENDOR) === (int) $vendor->id
                    || ((int) $item->get_meta(\Cheppi\Sellers\Infrastructure\WordPress\Orders\OrderSync::META_VENDOR) === 0 && (int) get_post_field('post_author', $item->get_product_id()) === $vendor->userId)
            );
            $sections[] = ['vendor' => $vendor, 'html' => View::capture('panel/order', [
                'vendor' => $vendor,
                'part' => $part,
                'order' => $order,
                'items' => $items,
                'entries' => array_values(array_filter($entries, static fn ($e): bool => $e->vendorId === (int) $vendor->id)),
                'adminVendorId' => (int) $vendor->id,
            ])];
        }
        $this->embed('pedidos', sprintf(__('Pedido #%d', 'cheppi-sellers'), $orderId), static function () use ($order, $sections): void {
            echo View::capture('manage/order', ['order' => $order, 'sections' => $sections]); // phpcs:ignore WordPress.Security.EscapeOutput
        });
    }

    /**
     * Prints the management layout around the output of $render.
     *
     * @param callable(): void   $render
     * @param array<string, mixed>|null $flash
     */
    private function embed(string $nav, string $title, callable $render, ?array $flash = null): never
    {
        ob_start();
        $render();
        $content = (string) ob_get_clean();
        status_header(200);
        echo View::capture('panel/layout', [ // phpcs:ignore WordPress.Security.EscapeOutput -- templates escape
            'title' => $title,
            'view' => 'manage-' . $nav,
            'nav' => $nav,
            'content' => $content,
            'withShell' => true,
            'assets' => $this->assets,
            'vendor' => null,
            'user' => wp_get_current_user(),
            'flash' => $flash ?? Flash::take(),
            'extraCss' => ['admin.css'],
            'extraJs' => ['admin.js'],
            'bodyClass' => 'ch-manage',
            'userLabel' => __('Equipo Cheppi', 'cheppi-sellers'),
            'navItems' => [
                'inicio' => ['home', __('Inicio', 'cheppi-sellers'), Routes::manageUrl(), true],
                'tiendas' => ['store', __('Tiendas', 'cheppi-sellers'), Routes::manageUrl('tiendas'), true],
                'productos' => ['box', __('Productos', 'cheppi-sellers'), Routes::manageUrl('productos'), true],
                'pedidos' => ['receipt', __('Pedidos', 'cheppi-sellers'), Routes::manageUrl('pedidos'), true],
                'liquidaciones' => ['wallet', __('Liquidaciones', 'cheppi-sellers'), Routes::manageUrl('liquidaciones'), true],
                'campanas' => ['tag', __('Campañas', 'cheppi-sellers'), Routes::manageUrl('campanas'), true],
                'reglas' => ['chart', __('Reglas', 'cheppi-sellers'), Routes::manageUrl('reglas'), true],
            ],
            'footItems' => [
                ['store', __('Ver el sitio', 'cheppi-sellers'), home_url('/')],
                ['help', __('WordPress (avanzado)', 'cheppi-sellers'), admin_url()],
                ['logout', __('Salir', 'cheppi-sellers'), wp_logout_url(home_url('/'))],
            ],
        ]);
        exit;
    }
}
