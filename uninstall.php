<?php
/**
 * Runs when the plugin is deleted from wp-admin.
 * Sales and payout records are kept unless the constant CHEPPI_SELLERS_DELETE_DATA is true in wp-config.php,
 * because they are accounting records.
 */
declare(strict_types=1);

defined('WP_UNINSTALL_PLUGIN') || exit;

if (!defined('CHEPPI_SELLERS_DELETE_DATA') || CHEPPI_SELLERS_DELETE_DATA !== true) {
    return;
}

global $wpdb;
foreach (['cheppi_vendors', 'cheppi_ledger', 'cheppi_payouts', 'cheppi_rules_history', 'cheppi_vendor_documents', 'cheppi_fulfillments'] as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}
delete_option('cheppi_business_rules');
delete_option('cheppi_sellers_db_version');
remove_role('cheppi_vendor');
