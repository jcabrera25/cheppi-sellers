<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Persistence;

use Cheppi\Sellers\Application\DeliveryZones;
use Cheppi\Sellers\Domain\Shipping\DeliveryZone;
use Cheppi\Sellers\Infrastructure\WordPress\Installer;

/** Zones as JSON in the vendors table; cached per request because the cart asks once per package. */
final class WpDeliveryZones implements DeliveryZones
{
    /** @var array<int, list<DeliveryZone>> */
    private array $cache = [];

    public function forVendor(int $vendorId): array
    {
        if (!isset($this->cache[$vendorId])) {
            global $wpdb;
            $table = Installer::tables()['vendors'];
            $json = (string) $wpdb->get_var($wpdb->prepare("SELECT delivery_zones FROM {$table} WHERE id = %d", $vendorId));
            $rows = json_decode($json, true);
            $this->cache[$vendorId] = is_array($rows) ? array_map([DeliveryZone::class, 'fromArray'], array_values(array_filter($rows, 'is_array'))) : [];
        }
        return $this->cache[$vendorId];
    }

    public function save(int $vendorId, array $zones): void
    {
        global $wpdb;
        $wpdb->update(
            Installer::tables()['vendors'],
            ['delivery_zones' => wp_json_encode(array_map(static fn (DeliveryZone $z): array => $z->toArray(), $zones))],
            ['id' => $vendorId]
        );
        $this->cache[$vendorId] = $zones;
    }
}
