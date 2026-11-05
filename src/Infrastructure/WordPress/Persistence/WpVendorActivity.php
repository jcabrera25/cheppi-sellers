<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Persistence;

use Cheppi\Sellers\Application\VendorActivity;
use Cheppi\Sellers\Infrastructure\WordPress\Installer;

final class WpVendorActivity implements VendorActivity
{
    public function hasFinancialHistory(int $vendorId): bool
    {
        global $wpdb;
        $t = Installer::tables();
        return (bool) $wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$t['ledger']} WHERE vendor_id = %d LIMIT 1", $vendorId))
            || (bool) $wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$t['payouts']} WHERE vendor_id = %d LIMIT 1", $vendorId));
    }
}
