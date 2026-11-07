<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Front;

/**
 * Pretty URLs:
 *   /vendedor/                      panel home
 *   /vendedor/registro/             registration
 *   /vendedor/productos/            products list
 *   /vendedor/productos/nuevo/      new product
 *   /vendedor/productos/<id>/       edit product
 *   /vendedor/mi-tienda/            storefront settings
 *   /vendedor/pedidos/              orders
 *   /vendedor/pedidos/<id>/         one order (the vendor's part)
 *   /vendedor/envios/               delivery zones and rates
 *   /vendedor/pagos/                balance, bank account, payouts and statements
 *   /vendedor/promociones/          coupons, campaigns, featured products
 *   /vendedor/metricas/             sales and fulfilment metrics
 *   /vendedor/productos/importar/   CSV import/export
 *   /marca/<slug>/                  premium brand page (a WordPress page designed by Cheppi)
 *   /tienda/                        store directory (WordPress page "Tiendas")
 *   /tienda/<slug>/                 public store page
 */
final class Routes
{
    public const QUERY_VAR = 'cheppi_panel';
    public const ID_VAR = 'cheppi_id';
    public const STORE_VAR = 'cheppi_store';
    public const BASE = 'vendedor';
    public const STORE_BASE = 'tienda';
    public const BRAND_BASE = 'marca';
    public const BRAND_VAR = 'cheppi_brand';

    public static function register(): void
    {
        $panel = 'index.php?' . self::QUERY_VAR . '=';
        add_rewrite_rule('^' . self::BASE . '/?$', $panel . 'inicio', 'top');
        add_rewrite_rule('^' . self::BASE . '/registro/?$', $panel . 'registro', 'top');
        add_rewrite_rule('^' . self::BASE . '/productos/?$', $panel . 'productos', 'top');
        add_rewrite_rule('^' . self::BASE . '/productos/nuevo/?$', $panel . 'producto&' . self::ID_VAR . '=0', 'top');
        add_rewrite_rule('^' . self::BASE . '/productos/([0-9]+)/?$', $panel . 'producto&' . self::ID_VAR . '=$matches[1]', 'top');
        add_rewrite_rule('^' . self::BASE . '/mi-tienda/?$', $panel . 'mi-tienda', 'top');
        add_rewrite_rule('^' . self::BASE . '/pedidos/?$', $panel . 'pedidos', 'top');
        add_rewrite_rule('^' . self::BASE . '/pedidos/([0-9]+)/?$', $panel . 'pedido&' . self::ID_VAR . '=$matches[1]', 'top');
        add_rewrite_rule('^' . self::BASE . '/envios/?$', $panel . 'envios', 'top');
        add_rewrite_rule('^' . self::BASE . '/pagos/?$', $panel . 'pagos', 'top');
        add_rewrite_rule('^' . self::BASE . '/promociones/?$', $panel . 'promociones', 'top');
        add_rewrite_rule('^' . self::BASE . '/metricas/?$', $panel . 'metricas', 'top');
        add_rewrite_rule('^' . self::BASE . '/productos/importar/?$', $panel . 'importar', 'top');
        add_rewrite_rule('^' . self::BRAND_BASE . '/([a-z0-9-]+)/?$', 'index.php?' . self::BRAND_VAR . '=$matches[1]', 'top');
        // Store URLs resolve to the "Tiendas" page so themes render them like any page.
        $storePage = StorePage::id();
        add_rewrite_rule(
            '^' . self::STORE_BASE . '/([a-z0-9-]+)/?$',
            'index.php?' . ($storePage > 0 ? 'page_id=' . $storePage . '&' : '') . self::STORE_VAR . '=$matches[1]',
            'top'
        );

        add_filter('query_vars', static function (array $vars): array {
            array_push($vars, self::QUERY_VAR, self::ID_VAR, self::STORE_VAR, self::BRAND_VAR);
            return $vars;
        });

        // Kept as an autoloaded '0' instead of deleted: a missing option costs a query on every request.
        if (get_option('cheppi_sellers_flush_rewrites') === '1') {
            flush_rewrite_rules(false);
            update_option('cheppi_sellers_flush_rewrites', '0', true);
        }
    }

    public static function panelUrl(string $path = ''): string
    {
        return home_url('/' . self::BASE . '/' . ($path !== '' ? trailingslashit($path) : ''));
    }

    public static function registerUrl(): string
    {
        return self::panelUrl('registro');
    }

    public static function productsUrl(): string
    {
        return self::panelUrl('productos');
    }

    public static function productUrl(int $id = 0): string
    {
        return self::panelUrl($id > 0 ? 'productos/' . $id : 'productos/nuevo');
    }

    public static function storeSettingsUrl(): string
    {
        return self::panelUrl('mi-tienda');
    }

    public static function ordersUrl(): string
    {
        return self::panelUrl('pedidos');
    }

    public static function orderUrl(int $orderId): string
    {
        return self::panelUrl('pedidos/' . $orderId);
    }

    public static function shippingUrl(): string
    {
        return self::panelUrl('envios');
    }

    public static function paymentsUrl(): string
    {
        return self::panelUrl('pagos');
    }

    public static function promotionsUrl(): string
    {
        return self::panelUrl('promociones');
    }

    public static function metricsUrl(): string
    {
        return self::panelUrl('metricas');
    }

    public static function importUrl(): string
    {
        return self::panelUrl('productos/importar');
    }

    public static function brandUrl(string $slug): string
    {
        return home_url('/' . self::BRAND_BASE . '/' . $slug . '/');
    }

    public static function storeUrl(string $slug): string
    {
        return home_url('/' . self::STORE_BASE . '/' . $slug . '/');
    }
}
