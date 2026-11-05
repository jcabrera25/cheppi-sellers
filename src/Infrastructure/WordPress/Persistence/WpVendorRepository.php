<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Persistence;

use Cheppi\Sellers\Application\VendorRepository;
use Cheppi\Sellers\Domain\Vendor\Vendor;
use Cheppi\Sellers\Domain\Vendor\VendorStatus;
use Cheppi\Sellers\Infrastructure\WordPress\Installer;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Vendors in wp_cheppi_vendors. Dates are stored in UTC.
 */
final class WpVendorRepository implements VendorRepository
{
    /** @var array<int, Vendor> request-level cache by user id */
    private array $byUser = [];

    public function save(Vendor $vendor): Vendor
    {
        global $wpdb;
        $table = Installer::tables()['vendors'];
        $row = [
            'user_id' => $vendor->userId,
            'store_name' => $vendor->storeName,
            'store_slug' => $vendor->storeSlug,
            'status' => $vendor->status->value,
            'plan_id' => $vendor->planId,
            'commission_override_bps' => $vendor->commissionOverrideBps,
            'ruc' => $vendor->ruc,
            'legal_name' => $vendor->legalName,
            'phone' => $vendor->phone,
            'category' => $vendor->category,
            'district' => $vendor->district,
            'instagram' => $vendor->instagram,
            'website' => $vendor->website,
            'status_note' => $vendor->statusNote,
            'created_at' => self::toDb($vendor->createdAt),
            'updated_at' => self::toDb($vendor->updatedAt),
            'approved_at' => $vendor->approvedAt ? self::toDb($vendor->approvedAt) : null,
        ];

        if ($vendor->id === null) {
            $wpdb->insert($table, $row);
            $vendor = $vendor->withId((int) $wpdb->insert_id);
        } else {
            $wpdb->update($table, $row, ['id' => $vendor->id]);
        }
        // wpdb writes PHP null as SQL NULL (WordPress 4.4+), so no extra queries are needed.
        $this->byUser[$vendor->userId] = $vendor;
        return $vendor;
    }

    public function find(int $id): ?Vendor
    {
        global $wpdb;
        $table = Installer::tables()['vendors'];
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
        return is_array($row) ? self::hydrate($row) : null;
    }

    public function findByUserId(int $userId): ?Vendor
    {
        if ($userId <= 0) {
            return null;
        }
        if (array_key_exists($userId, $this->byUser)) {
            return $this->byUser[$userId];
        }
        global $wpdb;
        $table = Installer::tables()['vendors'];
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE user_id = %d", $userId), ARRAY_A);
        $vendor = is_array($row) ? self::hydrate($row) : null;
        if ($vendor !== null) {
            $this->byUser[$userId] = $vendor;
        }
        return $vendor;
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        global $wpdb;
        $table = Installer::tables()['vendors'];
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$table} WHERE store_slug = %s AND id <> %d LIMIT 1",
            $slug,
            $exceptId ?? 0
        ));
    }

    public function rucExists(string $ruc, ?int $exceptId = null): bool
    {
        global $wpdb;
        $table = Installer::tables()['vendors'];
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$table} WHERE ruc = %s AND status <> %s AND id <> %d LIMIT 1",
            $ruc,
            VendorStatus::Rejected->value,
            $exceptId ?? 0
        ));
    }

    public function list(?VendorStatus $status, int $limit, int $offset, string $search = ''): array
    {
        global $wpdb;
        $table = Installer::tables()['vendors'];
        $where = ['1=1'];
        $args = [];
        if ($status !== null) {
            $where[] = 'status = %s';
            $args[] = $status->value;
        }
        $search = trim($search);
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(store_name LIKE %s OR legal_name LIKE %s OR ruc LIKE %s)';
            array_push($args, $like, $like, $like);
        }
        $args[] = $limit;
        $args[] = $offset;
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE " . implode(' AND ', $where) . ' ORDER BY created_at DESC LIMIT %d OFFSET %d',
            ...$args
        );
        return array_map([self::class, 'hydrate'], (array) $wpdb->get_results($sql, ARRAY_A));
    }

    public function delete(int $id): void
    {
        global $wpdb;
        $vendor = $this->find($id);
        $wpdb->delete(Installer::tables()['vendors'], ['id' => $id], ['%d']);
        if ($vendor !== null) {
            unset($this->byUser[$vendor->userId]);
        }
    }

    public function countByStatus(): array
    {
        global $wpdb;
        $table = Installer::tables()['vendors'];
        $counts = [];
        foreach ((array) $wpdb->get_results("SELECT status, COUNT(*) AS n FROM {$table} GROUP BY status", ARRAY_A) as $row) {
            $counts[(string) $row['status']] = (int) $row['n'];
        }
        return $counts;
    }

    /** @param array<string, mixed> $row */
    private static function hydrate(array $row): Vendor
    {
        return new Vendor(
            (int) $row['id'],
            (int) $row['user_id'],
            (string) $row['store_name'],
            (string) $row['store_slug'],
            VendorStatus::tryFrom((string) $row['status']) ?? VendorStatus::Pending,
            (string) $row['plan_id'],
            $row['commission_override_bps'] === null ? null : (int) $row['commission_override_bps'],
            (string) $row['ruc'],
            (string) $row['legal_name'],
            (string) $row['phone'],
            (string) ($row['category'] ?? ''),
            (string) ($row['district'] ?? ''),
            (string) ($row['instagram'] ?? ''),
            (string) ($row['website'] ?? ''),
            (string) ($row['status_note'] ?? ''),
            self::fromDb((string) $row['created_at']),
            self::fromDb((string) $row['updated_at']),
            $row['approved_at'] ? self::fromDb((string) $row['approved_at']) : null
        );
    }

    private static function toDb(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private static function fromDb(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }
}
