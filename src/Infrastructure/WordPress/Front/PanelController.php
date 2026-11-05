<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Front;

use Cheppi\Sellers\Application\BusinessRulesRepository;
use Cheppi\Sellers\Application\DocumentStore;
use Cheppi\Sellers\Application\VendorRepository;
use Cheppi\Sellers\Domain\Catalog\ProductStatus;
use Cheppi\Sellers\Domain\Vendor\Vendor;
use Cheppi\Sellers\Infrastructure\WordPress\Assets;
use Cheppi\Sellers\Infrastructure\WordPress\Catalog\VendorStats;
use Cheppi\Sellers\Infrastructure\WordPress\Catalog\WcProductCatalog;
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
        private readonly Assets $assets
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
            'mi-tienda' => $this->renderPage('storefront', __('Mi tienda', 'cheppi-sellers'), $base, 'mi-tienda'),
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
        ], 'productos');
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
