<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Persistence;

use Cheppi\Sellers\Application\Ledger;
use Cheppi\Sellers\Domain\Ledger\Balance;
use Cheppi\Sellers\Domain\Ledger\LedgerEntry;
use Cheppi\Sellers\Domain\Ledger\LedgerEntryType;
use Cheppi\Sellers\Domain\Money\Money;
use Cheppi\Sellers\Infrastructure\WordPress\Installer;
use DateTimeImmutable;
use DateTimeZone;

/** Ledger in {prefix}cheppi_ledger. Dates are stored in UTC. */
final class WpLedger implements Ledger
{
    public function add(array $entries): void
    {
        if ($entries === []) {
            return;
        }
        global $wpdb;
        $table = Installer::tables()['ledger'];
        $values = [];
        $args = [];
        foreach ($entries as $e) {
            $values[] = '(%d, %s, %d, %s, ' . ($e->commissionBps === null ? 'NULL' : '%d') . ', ' . ($e->orderId === null ? 'NULL' : '%d') . ', ' . ($e->orderItemId === null ? 'NULL' : '%d') . ', ' . ($e->payoutId === null ? 'NULL' : '%d') . ', %s, %s, %s)';
            array_push($args, $e->vendorId, $e->type->value, $e->amount->cents, $e->amount->currency);
            foreach ([$e->commissionBps, $e->orderId, $e->orderItemId, $e->payoutId] as $nullable) {
                if ($nullable !== null) {
                    $args[] = $nullable;
                }
            }
            array_push($args, mb_substr($e->note, 0, 255), self::toDb($e->createdAt), self::toDb($e->availableAt));
        }
        // One INSERT for the whole order: atomic without an explicit transaction.
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (vendor_id, type, amount_cents, currency, commission_bps, order_id, order_item_id, payout_id, note, created_at, available_at) VALUES " . implode(', ', $values),
            ...$args
        ));
    }

    public function forOrder(int $orderId): array
    {
        global $wpdb;
        $table = Installer::tables()['ledger'];
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE order_id = %d ORDER BY id", $orderId), ARRAY_A);
        return array_map([self::class, 'hydrate'], (array) $rows);
    }

    public function release(int $orderId, int $vendorId, DateTimeImmutable $availableAt): void
    {
        global $wpdb;
        $table = Installer::tables()['ledger'];
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET available_at = %s WHERE order_id = %d AND vendor_id = %d AND available_at = %s",
            self::toDb($availableAt),
            $orderId,
            $vendorId,
            LedgerEntry::UNTIL_DELIVERED
        ));
    }

    public function balance(int $vendorId, DateTimeImmutable $now): Balance
    {
        global $wpdb;
        $table = Installer::tables()['ledger'];
        $immediate = "'" . implode("','", array_map(static fn (LedgerEntryType $t): string => $t->value, array_filter(LedgerEntryType::cases(), static fn (LedgerEntryType $t): bool => $t->isImmediateDebit()))) . "'";
        // Same rule as Balance::fromEntries, computed by the database.
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN available_at <= %s OR (amount_cents < 0 AND type IN ({$immediate})) THEN amount_cents ELSE 0 END), 0) AS available,
                COALESCE(SUM(CASE WHEN available_at <= %s OR (amount_cents < 0 AND type IN ({$immediate})) THEN 0 ELSE amount_cents END), 0) AS pending
             FROM {$table} WHERE vendor_id = %d",
            self::toDb($now),
            self::toDb($now),
            $vendorId
        ), ARRAY_A);
        return new Balance(Money::ofCents((int) ($row['available'] ?? 0)), Money::ofCents((int) ($row['pending'] ?? 0)));
    }

    /** SQL condition shared by every "payable at cut-off" query (same rule as LedgerEntry::isPayableAt). */
    private static function payableWhere(): string
    {
        $immediate = "'" . implode("','", array_map(static fn (LedgerEntryType $t): string => $t->value, array_filter(LedgerEntryType::cases(), static fn (LedgerEntryType $t): bool => $t->isImmediateDebit()))) . "'";
        return "payout_id IS NULL AND (available_at <= %s OR (amount_cents < 0 AND type IN ({$immediate})))";
    }

    public function vendorsWithPayable(DateTimeImmutable $cutoff): array
    {
        global $wpdb;
        $table = Installer::tables()['ledger'];
        $ids = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT vendor_id FROM {$table} WHERE " . self::payableWhere(), self::toDb($cutoff)));
        return array_map('intval', (array) $ids);
    }

    public function payable(int $vendorId, DateTimeImmutable $cutoff): array
    {
        global $wpdb;
        $table = Installer::tables()['ledger'];
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE vendor_id = %d AND " . self::payableWhere() . ' ORDER BY id', $vendorId, self::toDb($cutoff)), ARRAY_A);
        return array_map([self::class, 'hydrate'], (array) $rows);
    }

    public function assignPayout(array $entryIds, int $payoutId): void
    {
        $entryIds = array_values(array_filter(array_map('intval', $entryIds)));
        if ($entryIds === []) {
            return;
        }
        global $wpdb;
        $table = Installer::tables()['ledger'];
        $in = implode(',', array_fill(0, count($entryIds), '%d'));
        $wpdb->query($wpdb->prepare("UPDATE {$table} SET payout_id = %d WHERE payout_id IS NULL AND id IN ({$in})", $payoutId, ...$entryIds));
    }

    public function releasePayout(int $payoutId): void
    {
        global $wpdb;
        $table = Installer::tables()['ledger'];
        $wpdb->query($wpdb->prepare("UPDATE {$table} SET payout_id = NULL WHERE payout_id = %d AND type <> %s", $payoutId, LedgerEntryType::Payout->value));
    }

    public function forPayout(int $payoutId): array
    {
        global $wpdb;
        $table = Installer::tables()['ledger'];
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE payout_id = %d ORDER BY created_at, id", $payoutId), ARRAY_A);
        return array_map([self::class, 'hydrate'], (array) $rows);
    }

    public function forVendor(int $vendorId, int $limit, int $offset = 0): array
    {
        global $wpdb;
        $table = Installer::tables()['ledger'];
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE vendor_id = %d ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d", $vendorId, $limit, $offset), ARRAY_A);
        return array_map([self::class, 'hydrate'], (array) $rows);
    }

    /**
     * Money per order part, for reports: net to the vendor (sales − commission + shipping − refunds) and commission.
     *
     * @param list<int> $orderIds
     * @return array<string, array{net: int, commission: int}> keyed "orderId:vendorId"
     */
    public function partTotals(array $orderIds): array
    {
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
        if ($orderIds === []) {
            return [];
        }
        global $wpdb;
        $table = Installer::tables()['ledger'];
        $in = implode(',', array_fill(0, count($orderIds), '%d'));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT order_id, vendor_id,
                    SUM(CASE WHEN type IN ('sale','commission','shipping','refund') THEN amount_cents ELSE 0 END) AS net,
                    -SUM(CASE WHEN type = 'commission' THEN amount_cents ELSE 0 END) AS commission
             FROM {$table} WHERE order_id IN ({$in}) GROUP BY order_id, vendor_id",
            ...$orderIds
        ), ARRAY_A);
        $out = [];
        foreach ((array) $rows as $row) {
            $out[$row['order_id'] . ':' . $row['vendor_id']] = ['net' => (int) $row['net'], 'commission' => (int) $row['commission']];
        }
        return $out;
    }

    /** @param array<string, string|null> $row */
    private static function hydrate(array $row): LedgerEntry
    {
        $utc = new DateTimeZone('UTC');
        return new LedgerEntry(
            (int) $row['vendor_id'],
            LedgerEntryType::from((string) $row['type']),
            Money::ofCents((int) $row['amount_cents'], (string) $row['currency']),
            new DateTimeImmutable((string) $row['created_at'], $utc),
            new DateTimeImmutable((string) $row['available_at'], $utc),
            $row['order_id'] !== null ? (int) $row['order_id'] : null,
            $row['order_item_id'] !== null ? (int) $row['order_item_id'] : null,
            $row['commission_bps'] !== null ? (int) $row['commission_bps'] : null,
            (string) $row['note'],
            (int) $row['id'],
            $row['payout_id'] !== null ? (int) $row['payout_id'] : null
        );
    }

    private static function toDb(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
