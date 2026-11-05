<?php
/**
 * Plugin Name:       Cheppi Sellers
 * Plugin URI:        https://cheppimarket.ccrea.pe
 * Description:       Marketplace multivendedor de Cheppi Market: vendedores, comisiones, libro contable, liquidaciones y envíos por marca.
 * Version:           0.2.0
 * Requires at least: 7.0
 * Requires PHP:      8.2
 * Author:            Ccrea
 * Text Domain:       cheppi-sellers
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 * WC requires at least: 10.0
 * WC tested up to:   11.2
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

define('CHEPPI_SELLERS_VERSION', '0.2.0');
define('CHEPPI_SELLERS_FILE', __FILE__);
define('CHEPPI_SELLERS_DIR', __DIR__);

if (version_compare(PHP_VERSION, '8.2', '<')) {
    add_action('admin_notices', static function (): void {
        echo '<div class="notice notice-error"><p>'
            . esc_html__('Cheppi Sellers necesita PHP 8.2 o superior.', 'cheppi-sellers')
            . '</p></div>';
    });
    return;
}

require __DIR__ . '/src/autoload.php';

use Cheppi\Sellers\Infrastructure\WordPress\Installer;
use Cheppi\Sellers\Infrastructure\WordPress\Plugin;

register_activation_hook(__FILE__, [Installer::class, 'activate']);
register_deactivation_hook(__FILE__, [Installer::class, 'deactivate']);

// Declare compatibility with WooCommerce High-Performance Order Storage.
add_action('before_woocommerce_init', static function (): void {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

add_action('plugins_loaded', static function (): void {
    Plugin::instance()->boot();
}, 20);
