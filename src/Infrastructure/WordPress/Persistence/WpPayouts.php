<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Persistence;

use Cheppi\Sellers\Application\Payouts;
use Cheppi\Sellers\Domain\Money\Money;
use Cheppi\Sellers\Domain\Payout\Payout;
use Cheppi\Sellers\Domain\Payout\PayoutStatus;
use Cheppi\Sellers\Infrastructure\WordPress\Installer;
use DateTimeImmutable;
use DateTimeZone;

final class WpPayouts implements Payouts
{
    public function save(Payout $p): Payout
    {
        global $wpdb;
        $table = Installer::tables()['payouts'];
        $row = [
            'vendor_id' => $p->vendorId,
            'amount_cents' => $p->amount->cents,
            'currency' => $p->amount->currency,
            'status' => $p->status->value,
            'cutoff_date' => $p->cutoffDate->format('Y-m-d'),
            'reference' => $p->reference,
            'created_at' => self::toDb($p->createdAt),
            'paid_at' => $p->paidAt !== null ? self::toDb($p->paidAt) : null,
        ];
        if ($p->id === null) {
            $wpdb->insert($table, $row);
            return $p->withId((int) $wpdb->insert_id);
        }
        $wpdb->update($table, $row, ['id' => $p->id]);
        return $p;
    }

    public function find(int $id): ?Payout
    {
        global $wpdb;
        $table = Installer::tables()['payouts'];
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
        return is_array($row) ? self::hydrate($row) : null;
    }

    public function existsFor(int $vendorId, DateTimeImmutable $cutoff): bool
    {
        global $wpdb;
        $table = Installer::tables()['payouts'];
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$table} WHERE vendor_id = %d AND cutoff_date = %s AND status <> %s LIMIT 1",
            $vendorId,
            $cutoff->format('Y-m-d'),
            PayoutStatus::Cancelled->value
        ));
    }

    public function list(?PayoutStatus $status, ?int $vendorId, int $limit, int $offset): array
    {
        global $wpdb;
        $table = Installer::tables()['payouts'];
        $where = ['1=1'];
        $args = [];
        if ($status !== null) {
            $where[] = 'status = %s';
            $args[] = $status->value;
        }
        if ($vendorId !== null) {
            $where[] = 'vendor_id = %d';
            $args[] = $vendorId;
        }
        array_push($args, $limit, $offset);
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE " . implode(' AND ', $where) . ' ORDER BY cutoff_date DESC, id DESC LIMIT %d OFFSET %d', ...$args), ARRAY_A);
        return array_map([self::class, 'hydrate'], (array) $rows);
    }

    public function totals(): array
    {
        global $wpdb;
        $table = Installer::tables()['payouts'];
        $out = [];
        foreach ((array) $wpdb->get_results("SELECT status, COUNT(*) AS n, SUM(amount_cents) AS c FROM {$table} GROUP BY status", ARRAY_A) as $row) {
            $out[(string) $row['status']] = ['count' => (int) $row['n'], 'cents' => (int) $row['c']];
        }
        return $out;
    }

    /** @param array<string, string|null> $row */
    private static function hydrate(array $row): Payout
    {
        $utc = new DateTimeZone('UTC');
        return new Payout(
            (int) $row['vendor_id'],
            Money::ofCents((int) $row['amount_cents'], (string) $row['currency']),
            new DateTimeImmutable((string) $row['cutoff_date'], wp_timezone()),
            PayoutStatus::tryFrom((string) $row['status']) ?? PayoutStatus::Pending,
            (string) $row['reference'],
            new DateTimeImmutable((string) $row['created_at'], $utc),
            $row['paid_at'] !== null ? new DateTimeImmutable((string) $row['paid_at'], $utc) : null,
            (int) $row['id']
        );
    }

    private static function toDb(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
