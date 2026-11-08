<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Customers;

use Automattic\WooCommerce\Utilities\OrderUtil;
use Cheppi\Sellers\Infrastructure\WordPress\Installer;

/**
 * Customers = people who bought through the marketplace, grouped by billing e-mail (so guests count too).
 * Read straight from the orders tables joined with our fulfillments: one query per screen, nothing stored.
 * A store only ever sees customers that bought from it, with the orders and money of its own part.
 *
 * Each customer is addressed by an opaque key (16 hex characters of the e-mail's MD5), never by e-mail.
 */
final class WpCustomers
{
    public const SORTS = ['reciente', 'gasto', 'pedidos'];
    private const FIELD = "CHAR(31)";
    private const ROW = '#~#';

    public static function key(string $email): string
    {
        return substr(md5(strtolower(trim($email))), 0, 16);
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function list(?int $vendorId, string $search, string $sort, int $limit, int $offset): array
    {
        global $wpdb;
        $c = $this->columns();
        [$where, $args] = $this->where($c, $vendorId, $search);
        $order = match ($sort) {
            'gasto' => 'spent DESC, last_at DESC',
            'pedidos' => 'orders DESC, last_at DESC',
            default => 'last_at DESC',
        };
        $sql = $this->select($c) . " WHERE {$where} GROUP BY cheppi_ident ORDER BY {$order} LIMIT %d OFFSET %d";
        $rows = (array) $wpdb->get_results($wpdb->prepare($sql, ...array_merge($args, [$limit, $offset])), ARRAY_A);
        $count = "SELECT COUNT(DISTINCT {$c['ident']}) FROM " . $this->from($c) . " WHERE {$where}";
        $total = (int) ($args === [] ? $wpdb->get_var($count) : $wpdb->get_var($wpdb->prepare($count, ...$args)));
        return ['items' => array_map([$this, 'hydrate'], $rows), 'total' => $total];
    }

    /** One customer (null when unknown, or when they never bought from that store). */
    public function find(string $key, ?int $vendorId): ?array
    {
        global $wpdb;
        $c = $this->columns();
        [$where, $args] = $this->where($c, $vendorId, '');
        $sql = $this->select($c) . " WHERE {$where} AND LEFT(MD5({$c['ident']}), 16) = %s GROUP BY cheppi_ident LIMIT 1";
        $row = $wpdb->get_row($wpdb->prepare($sql, ...array_merge($args, [$key])), ARRAY_A);
        return is_array($row) ? $this->hydrate($row) : null;
    }

    /** @return list<int> the customer's orders (only the ones with that store when given) */
    public function orderIds(string $key, ?int $vendorId): array
    {
        global $wpdb;
        $c = $this->columns();
        [$where, $args] = $this->where($c, $vendorId, '');
        $sql = 'SELECT DISTINCT f.order_id FROM ' . $this->from($c) . " WHERE {$where} AND LEFT(MD5({$c['ident']}), 16) = %s";
        return array_map('intval', (array) $wpdb->get_col($wpdb->prepare($sql, ...array_merge($args, [$key]))));
    }

    /** @param array<string, string> $c */
    private function select(array $c): string
    {
        $latest = sprintf(
            "SUBSTRING_INDEX(GROUP_CONCAT(CONCAT_WS(%s, IFNULL(%s,''), IFNULL(%s,''), IFNULL(%s,''), IFNULL(%s,''), IFNULL(%s,''), IFNULL(%s,'')) ORDER BY f.order_id DESC SEPARATOR '%s'), '%s', 1)",
            self::FIELD, $c['first'], $c['last'], $c['city'], $c['state'], $c['country'], $c['phone'], self::ROW, self::ROW
        );
        return "SELECT {$c['ident']} AS cheppi_ident, MAX({$c['email']}) AS email, MAX(LEFT(MD5({$c['ident']}), 16)) AS k, MAX({$c['uid']}) AS uid,
                COUNT(DISTINCT f.order_id) AS orders,
                SUM(CASE WHEN f.status <> 'cancelled' THEN f.subtotal_cents + f.shipping_cents ELSE 0 END) AS spent,
                MAX(f.created_at) AS last_at, MIN(f.created_at) AS first_at,
                GROUP_CONCAT(DISTINCT f.vendor_id) AS vendors, {$latest} AS latest
                FROM " . $this->from($c);
    }

    /**
     * @param array<string, string> $c
     * @return array{0: string, 1: list<int|string>}
     */
    private function where(array $c, ?int $vendorId, string $search): array
    {
        global $wpdb;
        $where = ['1=1'];
        $args = [];
        if ($vendorId !== null) {
            $where[] = 'f.vendor_id = %d';
            $args[] = $vendorId;
        }
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like(strtolower($search)) . '%';
            $where[] = "({$c['email']} LIKE %s OR LOWER(CONCAT(IFNULL({$c['first']},''), ' ', IFNULL({$c['last']},''))) LIKE %s OR {$c['phone']} LIKE %s)";
            array_push($args, $like, $like, $like);
        }
        return [implode(' AND ', $where), $args];
    }

    /** @param array<string, string> $c */
    private function from(array $c): string
    {
        return Installer::tables()['fulfillments'] . ' f ' . $c['join'];
    }

    /**
     * SQL pieces for the order fields, for HPOS (custom order tables) or classic posts storage.
     *
     * @return array<string, string>
     */
    private function columns(): array
    {
        global $wpdb;
        if (class_exists(OrderUtil::class) && OrderUtil::custom_orders_table_usage_is_enabled()) {
            $cols = [
                'join' => "JOIN {$wpdb->prefix}wc_orders o ON o.id = f.order_id LEFT JOIN {$wpdb->prefix}wc_order_addresses a ON a.order_id = o.id AND a.address_type = 'billing'",
                // Orders created by some flows keep the e-mail only on the address row.
                'email' => "LOWER(TRIM(COALESCE(NULLIF(o.billing_email, ''), a.email, '')))",
                'uid' => 'o.customer_id',
                'first' => 'a.first_name',
                'last' => 'a.last_name',
                'city' => 'a.city',
                'state' => 'a.state',
                'country' => 'a.country',
                'phone' => 'a.phone',
            ];
            return $cols + ['ident' => self::ident($cols)];
        }
        $keys = ['email' => '_billing_email', 'uid' => '_customer_user', 'first' => '_billing_first_name', 'last' => '_billing_last_name', 'city' => '_billing_city', 'state' => '_billing_state', 'country' => '_billing_country', 'phone' => '_billing_phone'];
        $join = '';
        $cols = [];
        foreach ($keys as $alias => $metaKey) {
            $join .= " LEFT JOIN {$wpdb->postmeta} m_{$alias} ON m_{$alias}.post_id = f.order_id AND m_{$alias}.meta_key = '{$metaKey}'";
            $cols[$alias] = "m_{$alias}.meta_value";
        }
        $cols['email'] = "LOWER(TRIM(IFNULL({$cols['email']}, '')))";
        $cols['uid'] = "CAST(IFNULL({$cols['uid']}, 0) AS UNSIGNED)";
        return ['join' => $join, 'ident' => self::ident($cols)] + $cols;
    }

    /**
     * Who the customer is: the billing e-mail; without one (orders created by hand), the account, then the
     * phone with the name, then the order itself — so every order belongs to exactly one customer.
     *
     * @param array<string, string> $c
     */
    private static function ident(array $c): string
    {
        $phone = "REPLACE(REPLACE(IFNULL({$c['phone']}, ''), ' ', ''), '-', '')";
        return "(CASE WHEN {$c['email']} <> '' THEN {$c['email']}"
            . " WHEN {$c['uid']} > 0 THEN CONCAT('#u', {$c['uid']})"
            . " WHEN {$phone} <> '' THEN CONCAT('#t', {$phone}, LOWER(CONCAT(IFNULL({$c['first']}, ''), IFNULL({$c['last']}, ''))))"
            . " ELSE CONCAT('#o', f.order_id) END)";
    }

    /** @param array<string, string|null> $row */
    private function hydrate(array $row): array
    {
        $latest = explode(chr(31), (string) $row['latest']) + array_fill(0, 6, '');
        [$first, $last, $city, $state, $country, $phone] = array_map('trim', array_slice($latest, 0, 6));
        $uid = (int) $row['uid'];
        $user = $uid > 0 ? get_userdata($uid) : ((string) $row['email'] !== '' ? get_user_by('email', (string) $row['email']) : null);
        $tz = new \DateTimeZone('UTC');
        $date = static fn (?string $d): ?\DateTimeImmutable => $d ? new \DateTimeImmutable($d, $tz) : null;
        return [
            'key' => (string) $row['k'],
            'email' => (string) $row['email'],
            'first_name' => $first,
            'last_name' => $last,
            'name' => trim($first . ' ' . $last) ?: ((string) $row['email'] ?: __('Cliente sin nombre', 'cheppi-sellers')),
            'phone' => $phone,
            'user' => $user instanceof \WP_User ? $user : null,
            'location' => self::location($city, $state, $country),
            'orders' => (int) $row['orders'],
            'spent' => (int) $row['spent'],
            'last_order' => $date($row['last_at']),
            'first_order' => $date($row['first_at']),
            'vendors' => array_values(array_filter(array_map('intval', explode(',', (string) $row['vendors'])))),
        ];
    }

    private static function location(string $city, string $state, string $country): string
    {
        if ($state !== '' && function_exists('WC') && WC()->countries) {
            $states = WC()->countries->get_states($country !== '' ? $country : 'PE');
            $state = is_array($states) ? (string) ($states[$state] ?? $state) : $state;
        }
        return implode(', ', array_filter([$city, $state]));
    }
}
