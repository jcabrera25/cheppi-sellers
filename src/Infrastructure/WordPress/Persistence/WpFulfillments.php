<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Persistence;

use Cheppi\Sellers\Application\Fulfillments;
use Cheppi\Sellers\Domain\Money\Money;
use Cheppi\Sellers\Domain\Order\Fulfillment;
use Cheppi\Sellers\Domain\Order\FulfillmentStatus;
use Cheppi\Sellers\Infrastructure\WordPress\Installer;
use DateTimeImmutable;
use DateTimeZone;

/** Fulfillments in {prefix}cheppi_fulfillments (one row per order and vendor). */
final class WpFulfillments implements Fulfillments
{
    public function save(Fulfillment $f): Fulfillment
    {
        global $wpdb;
        $table = Installer::tables()['fulfillments'];
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (order_id, vendor_id, status, items_count, subtotal_cents, shipping_cents, currency, tracking, created_at, updated_at)
             VALUES (%d, %d, %s, %d, %d, %d, %s, %s, %s, %s)
             ON DUPLICATE KEY UPDATE
               shipped_at = IF(VALUES(status) = 'shipped' AND shipped_at IS NULL, VALUES(updated_at), shipped_at),
               delivered_at = IF(VALUES(status) = 'delivered' AND delivered_at IS NULL, VALUES(updated_at), delivered_at),
               status = VALUES(status), tracking = VALUES(tracking), updated_at = VALUES(updated_at)",
            $f->orderId,
            $f->vendorId,
            $f->status->value,
            $f->itemsCount,
            $f->subtotal->cents,
            $f->shipping->cents,
            $f->subtotal->currency,
            $f->tracking,
            self::toDb($f->createdAt),
            self::toDb($f->updatedAt)
        ));
        wp_cache_delete('counts_' . $f->vendorId, 'cheppi_fulfillments');
        return $f;
    }

    public function find(int $orderId, int $vendorId): ?Fulfillment
    {
        global $wpdb;
        $table = Installer::tables()['fulfillments'];
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE order_id = %d AND vendor_id = %d", $orderId, $vendorId), ARRAY_A);
        return is_array($row) ? self::hydrate($row) : null;
    }

    public function forOrder(int $orderId): array
    {
        global $wpdb;
        $table = Installer::tables()['fulfillments'];
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE order_id = %d", $orderId), ARRAY_A);
        return array_map([self::class, 'hydrate'], (array) $rows);
    }

    public function forVendor(int $vendorId, ?FulfillmentStatus $status, int $limit, int $offset): array
    {
        global $wpdb;
        $table = Installer::tables()['fulfillments'];
        $sql = "SELECT * FROM {$table} WHERE vendor_id = %d";
        $args = [$vendorId];
        if ($status !== null) {
            $sql .= ' AND status = %s';
            $args[] = $status->value;
        }
        $sql .= ' ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d';
        array_push($args, $limit, $offset);
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A);
        return array_map([self::class, 'hydrate'], (array) $rows);
    }

    public function countsForVendor(int $vendorId): array
    {
        $cached = wp_cache_get('counts_' . $vendorId, 'cheppi_fulfillments');
        if (is_array($cached)) {
            return $cached;
        }
        global $wpdb;
        $table = Installer::tables()['fulfillments'];
        $rows = $wpdb->get_results($wpdb->prepare("SELECT status, COUNT(*) AS n FROM {$table} WHERE vendor_id = %d GROUP BY status", $vendorId), ARRAY_A);
        $counts = [];
        foreach ((array) $rows as $row) {
            $counts[(string) $row['status']] = (int) $row['n'];
        }
        wp_cache_set('counts_' . $vendorId, $counts, 'cheppi_fulfillments');
        return $counts;
    }

    /** @param array<string, string> $row */
    private static function hydrate(array $row): Fulfillment
    {
        $utc = new DateTimeZone('UTC');
        return new Fulfillment(
            (int) $row['order_id'],
            (int) $row['vendor_id'],
            FulfillmentStatus::tryFrom((string) $row['status']) ?? FulfillmentStatus::New,
            (int) $row['items_count'],
            Money::ofCents((int) $row['subtotal_cents'], (string) $row['currency']),
            Money::ofCents((int) $row['shipping_cents'], (string) $row['currency']),
            (string) $row['tracking'],
            new DateTimeImmutable((string) $row['created_at'], $utc),
            new DateTimeImmutable((string) $row['updated_at'], $utc),
            (int) $row['id']
        );
    }

    private static function toDb(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
