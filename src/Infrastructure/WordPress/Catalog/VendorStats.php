<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Catalog;

use Cheppi\Sellers\Infrastructure\WordPress\Installer;

/**
 * Numbers for the seller dashboard, each in a single indexed query.
 */
final class VendorStats
{
    /** @return array<string, int> post status => count */
    public function productsByStatus(int $ownerId): array
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT post_status, COUNT(*) AS n FROM {$wpdb->posts}
             WHERE post_type = 'product' AND post_author = %d AND post_status IN ('draft','pending','publish')
             GROUP BY post_status",
            $ownerId
        ), ARRAY_A);
        $out = ['draft' => 0, 'pending' => 0, 'publish' => 0];
        foreach ((array) $rows as $row) {
            $out[(string) $row['post_status']] = (int) $row['n'];
        }
        return $out;
    }

    /** @return list<array{name: string, count: int}> published products per category, largest first */
    public function productsByCategory(int $ownerId): array
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT t.name, COUNT(*) AS n
             FROM {$wpdb->posts} p
             JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
             JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'product_cat'
             JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
             WHERE p.post_type = 'product' AND p.post_author = %d AND p.post_status = 'publish'
             GROUP BY t.term_id ORDER BY n DESC LIMIT 6",
            $ownerId
        ), ARRAY_A);
        return array_map(static fn (array $r): array => ['name' => (string) $r['name'], 'count' => (int) $r['n']], (array) $rows);
    }

    /** Products (or variations) with stock managed and at most $threshold units. */
    public function lowStock(int $ownerId, int $threshold = 3): int
    {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT COALESCE(NULLIF(p.post_parent, 0), p.ID))
             FROM {$wpdb->posts} p
             JOIN {$wpdb->posts} owner ON owner.ID = COALESCE(NULLIF(p.post_parent, 0), p.ID)
             JOIN {$wpdb->postmeta} ms ON ms.post_id = p.ID AND ms.meta_key = '_manage_stock' AND ms.meta_value = 'yes'
             JOIN {$wpdb->postmeta} st ON st.post_id = p.ID AND st.meta_key = '_stock'
             WHERE p.post_type IN ('product','product_variation') AND owner.post_author = %d
               AND owner.post_status = 'publish' AND CAST(st.meta_value AS SIGNED) <= %d",
            $ownerId,
            $threshold
        ));
    }

    /**
     * Sales per day from the ledger (filled when orders are attributed to vendors).
     *
     * @return list<array{date: string, cents: int}> oldest first, one row per day, zeros included
     */
    public function salesByDay(?int $vendorId, int $days = 14): array
    {
        global $wpdb;
        $table = Installer::tables()['ledger'];
        $tz = wp_timezone();
        $start = (new \DateTimeImmutable('today', $tz))->modify('-' . ($days - 1) . ' days');
        $vendorSql = $vendorId !== null ? $wpdb->prepare(' AND vendor_id = %d', $vendorId) : '';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(DATE_ADD(created_at, INTERVAL %d SECOND)) AS d, SUM(amount_cents) AS c FROM {$table}
             WHERE type = 'sale' AND created_at >= %s{$vendorSql}
               AND order_id NOT IN (SELECT order_id FROM {$table} WHERE type = 'refund'{$vendorSql})
             GROUP BY d",
            $tz->getOffset(new \DateTimeImmutable('now', $tz)),
            $start->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s')
        ), ARRAY_A);
        $byDay = [];
        foreach ((array) $rows as $row) {
            $byDay[(string) $row['d']] = (int) $row['c'];
        }
        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->modify("+{$i} days")->format('Y-m-d');
            $out[] = ['date' => $date, 'cents' => $byDay[$date] ?? 0];
        }
        return $out;
    }

    /**
     * Best sellers by amount (cancelled orders excluded).
     *
     * @return list<array{name: string, cents: int, qty: int}>
     */
    public function topProducts(int $vendorId, int $limit = 5): array
    {
        global $wpdb;
        $table = Installer::tables()['ledger'];
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT oi.order_item_name AS name, SUM(l.amount_cents) AS c, SUM(CAST(qty.meta_value AS UNSIGNED)) AS q
             FROM {$table} l
             JOIN {$wpdb->prefix}woocommerce_order_items oi ON oi.order_item_id = l.order_item_id
             LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta qty ON qty.order_item_id = l.order_item_id AND qty.meta_key = '_qty'
             WHERE l.vendor_id = %d AND l.type = 'sale'
               AND l.order_id NOT IN (SELECT order_id FROM {$table} WHERE vendor_id = %d AND type = 'refund')
             GROUP BY oi.order_item_name ORDER BY c DESC LIMIT %d",
            $vendorId,
            $vendorId,
            $limit
        ), ARRAY_A);
        return array_map(static fn (array $r): array => ['name' => (string) $r['name'], 'cents' => (int) $r['c'], 'qty' => (int) $r['q']], (array) $rows);
    }

    /**
     * Sales and fulfilment KPIs since $days ago. $vendorId null = whole marketplace (admin).
     *
     * @return array{orders: int, cancelled: int, delivered: int, units: int, sales: int, shipping: int, commission: int, avg_ticket: int, ship_hours: ?float, late: int}
     */
    public function metrics(?int $vendorId, int $days, int $dispatchHours): array
    {
        global $wpdb;
        $t = Installer::tables();
        $from = gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);
        $lateBefore = gmdate('Y-m-d H:i:s', time() - $dispatchHours * HOUR_IN_SECONDS);
        $vendorSql = $vendorId !== null ? $wpdb->prepare(' AND vendor_id = %d', $vendorId) : '';

        $f = (array) $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) AS orders,
                    SUM(status = 'cancelled') AS cancelled,
                    SUM(status = 'delivered') AS delivered,
                    SUM(CASE WHEN status <> 'cancelled' THEN items_count ELSE 0 END) AS units,
                    SUM(CASE WHEN status <> 'cancelled' THEN subtotal_cents ELSE 0 END) AS sales,
                    SUM(CASE WHEN status <> 'cancelled' THEN shipping_cents ELSE 0 END) AS shipping,
                    AVG(CASE WHEN shipped_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, created_at, shipped_at) END) AS ship_minutes
             FROM {$t['fulfillments']} WHERE created_at >= %s{$vendorSql}",
            $from
        ), ARRAY_A);
        $late = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$t['fulfillments']} WHERE status IN ('new', 'preparing') AND created_at < %s{$vendorSql}",
            $lateBefore
        ));
        $commission = -(int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount_cents), 0) FROM {$t['ledger']} WHERE type = 'commission' AND created_at >= %s{$vendorSql}",
            $from
        ));
        $orders = (int) ($f['orders'] ?? 0);
        $active = $orders - (int) ($f['cancelled'] ?? 0);
        return [
            'orders' => $orders,
            'cancelled' => (int) ($f['cancelled'] ?? 0),
            'delivered' => (int) ($f['delivered'] ?? 0),
            'units' => (int) ($f['units'] ?? 0),
            'sales' => (int) ($f['sales'] ?? 0),
            'shipping' => (int) ($f['shipping'] ?? 0),
            'commission' => $commission,
            'avg_ticket' => $active > 0 ? (int) round(((int) ($f['sales'] ?? 0)) / $active) : 0,
            'ship_hours' => $f['ship_minutes'] !== null ? round(((float) $f['ship_minutes']) / 60, 1) : null,
            'late' => $late,
        ];
    }

    /** @return list<array{vendor_id: int, orders: int, sales: int}> */
    public function topVendors(int $days, int $limit = 10): array
    {
        global $wpdb;
        $table = Installer::tables()['fulfillments'];
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT vendor_id, COUNT(*) AS orders, SUM(subtotal_cents) AS sales FROM {$table}
             WHERE created_at >= %s AND status <> 'cancelled' GROUP BY vendor_id ORDER BY sales DESC LIMIT %d",
            gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS),
            $limit
        ), ARRAY_A);
        return array_map(static fn (array $r): array => ['vendor_id' => (int) $r['vendor_id'], 'orders' => (int) $r['orders'], 'sales' => (int) $r['sales']], (array) $rows);
    }

    /** Open fulfilments older than the dispatch promise. @return list<array{order_id: int, vendor_id: int, status: string, created_at: string}> */
    public function lateFulfillments(int $dispatchHours, int $limit = 20): array
    {
        global $wpdb;
        $table = Installer::tables()['fulfillments'];
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT order_id, vendor_id, status, created_at FROM {$table} WHERE status IN ('new', 'preparing') AND created_at < %s ORDER BY created_at LIMIT %d",
            gmdate('Y-m-d H:i:s', time() - $dispatchHours * HOUR_IN_SECONDS),
            $limit
        ), ARRAY_A);
        return array_map(static fn (array $r): array => ['order_id' => (int) $r['order_id'], 'vendor_id' => (int) $r['vendor_id'], 'status' => (string) $r['status'], 'created_at' => (string) $r['created_at']], (array) $rows);
    }
}
