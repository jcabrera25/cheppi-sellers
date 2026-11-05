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
    public function salesByDay(int $vendorId, int $days = 14): array
    {
        global $wpdb;
        $table = Installer::tables()['ledger'];
        $tz = wp_timezone();
        $start = (new \DateTimeImmutable('today', $tz))->modify('-' . ($days - 1) . ' days');
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) AS d, SUM(amount_cents) AS c FROM {$table}
             WHERE vendor_id = %d AND type = 'sale' AND created_at >= %s GROUP BY DATE(created_at)",
            $vendorId,
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
}
