<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Front;

/**
 * Pretty URLs for the seller panel: /vendedor/ and /vendedor/registro/.
 */
final class Routes
{
    public const QUERY_VAR = 'cheppi_panel';
    public const BASE = 'vendedor';

    public static function register(): void
    {
        add_rewrite_rule('^' . self::BASE . '/?$', 'index.php?' . self::QUERY_VAR . '=inicio', 'top');
        add_rewrite_rule('^' . self::BASE . '/registro/?$', 'index.php?' . self::QUERY_VAR . '=registro', 'top');
        add_filter('query_vars', static function (array $vars): array {
            $vars[] = self::QUERY_VAR;
            return $vars;
        });

        if (get_option('cheppi_sellers_flush_rewrites') === '1') {
            flush_rewrite_rules(false);
            delete_option('cheppi_sellers_flush_rewrites');
        }
    }

    public static function panelUrl(): string
    {
        return home_url('/' . self::BASE . '/');
    }

    public static function registerUrl(): string
    {
        return home_url('/' . self::BASE . '/registro/');
    }
}
