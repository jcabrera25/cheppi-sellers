<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress;

use Cheppi\Sellers\Domain\Rules\BusinessRules;
use Cheppi\Sellers\Infrastructure\WordPress\Persistence\WpBusinessRulesRepository;

/**
 * Creates tables, the vendor role and default rules. Safe to run again (upgrades in place).
 */
final class Installer
{
    public const DB_VERSION = '1';
    public const DB_VERSION_OPTION = 'cheppi_sellers_db_version';
    public const VENDOR_ROLE = 'cheppi_vendor';

    public static function activate(): void
    {
        self::createTables();
        self::addRoles();
        if (get_option(WpBusinessRulesRepository::OPTION) === false) {
            add_option(WpBusinessRulesRepository::OPTION, BusinessRules::defaults()->toArray(), '', true);
        }
        update_option(self::DB_VERSION_OPTION, self::DB_VERSION, false);
    }

    public static function deactivate(): void
    {
        // Data and roles stay; uninstall.php removes them only if the admin chose so.
    }

    /** Runs on boot to apply schema changes after a plugin update. */
    public static function maybeUpgrade(): void
    {
        if (get_option(self::DB_VERSION_OPTION) !== self::DB_VERSION) {
            self::activate();
        }
    }

    /** @return array<string, string> logical name => table name with prefix */
    public static function tables(): array
    {
        global $wpdb;
        return [
            'vendors' => $wpdb->prefix . 'cheppi_vendors',
            'ledger' => $wpdb->prefix . 'cheppi_ledger',
            'payouts' => $wpdb->prefix . 'cheppi_payouts',
            'rules_history' => $wpdb->prefix . 'cheppi_rules_history',
        ];
    }

    private static function createTables(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $t = self::tables();

        // dbDelta needs two spaces after PRIMARY KEY and one field per line.
        dbDelta("CREATE TABLE {$t['vendors']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  store_name varchar(120) NOT NULL,
  store_slug varchar(140) NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'pending',
  plan_id varchar(32) NOT NULL,
  commission_override_bps smallint(5) unsigned DEFAULT NULL,
  ruc varchar(11) NOT NULL DEFAULT '',
  legal_name varchar(200) NOT NULL DEFAULT '',
  phone varchar(30) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  approved_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY user_id (user_id),
  UNIQUE KEY store_slug (store_slug),
  KEY status (status)
) $charset;");

        dbDelta("CREATE TABLE {$t['ledger']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  vendor_id bigint(20) unsigned NOT NULL,
  type varchar(20) NOT NULL,
  amount_cents bigint(20) NOT NULL,
  currency char(3) NOT NULL DEFAULT 'PEN',
  commission_bps smallint(5) unsigned DEFAULT NULL,
  order_id bigint(20) unsigned DEFAULT NULL,
  order_item_id bigint(20) unsigned DEFAULT NULL,
  payout_id bigint(20) unsigned DEFAULT NULL,
  note varchar(255) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  available_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY vendor_available (vendor_id,available_at),
  KEY order_id (order_id),
  KEY payout_id (payout_id)
) $charset;");

        dbDelta("CREATE TABLE {$t['payouts']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  vendor_id bigint(20) unsigned NOT NULL,
  amount_cents bigint(20) NOT NULL,
  currency char(3) NOT NULL DEFAULT 'PEN',
  status varchar(20) NOT NULL DEFAULT 'pending',
  cutoff_date date NOT NULL,
  reference varchar(120) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  paid_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY vendor_status (vendor_id,status),
  KEY cutoff_date (cutoff_date)
) $charset;");

        dbDelta("CREATE TABLE {$t['rules_history']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  changed_by bigint(20) unsigned NOT NULL,
  changed_at datetime NOT NULL,
  rules longtext NOT NULL,
  PRIMARY KEY  (id),
  KEY changed_at (changed_at)
) $charset;");
    }

    private static function addRoles(): void
    {
        if (get_role(self::VENDOR_ROLE) === null) {
            add_role(self::VENDOR_ROLE, __('Vendedor Cheppi', 'cheppi-sellers'), [
                'read' => true,
                'upload_files' => true,
            ]);
        }
        $admin = get_role('administrator');
        if ($admin !== null) {
            $admin->add_cap(Capabilities::MANAGE);
        }
        $shopManager = get_role('shop_manager');
        if ($shopManager !== null) {
            $shopManager->add_cap(Capabilities::MANAGE);
        }
    }
}
