<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Persistence;

use Cheppi\Sellers\Domain\Payout\BankDetails;
use Cheppi\Sellers\Infrastructure\WordPress\Installer;

/**
 * Vendor data that is not part of the Vendor entity: bank account, premium brand page, campaigns joined.
 * Stored in the vendors table; read in one query and cached per request.
 */
final class WpVendorExtras
{
    public const PREMIUM_REQUESTED = 'requested';
    public const PREMIUM_ACTIVE = 'active';

    /** @var array<int, array<string, mixed>> */
    private array $cache = [];

    public function bank(int $vendorId): ?BankDetails
    {
        $json = json_decode((string) ($this->row($vendorId)['bank_details'] ?? ''), true);
        return is_array($json) ? BankDetails::fromArray($json) : null;
    }

    public function saveBank(int $vendorId, BankDetails $bank): void
    {
        $this->update($vendorId, ['bank_details' => wp_json_encode($bank->toArray())]);
    }

    /** @return array{page_id: int, status: string} */
    public function premium(int $vendorId): array
    {
        $row = $this->row($vendorId);
        return ['page_id' => (int) ($row['premium_page_id'] ?? 0), 'status' => (string) ($row['premium_status'] ?? '')];
    }

    public function savePremium(int $vendorId, int $pageId, string $status): void
    {
        $this->update($vendorId, ['premium_page_id' => $pageId, 'premium_status' => $status]);
    }

    /** @return list<string> campaign ids the vendor joined */
    public function campaigns(int $vendorId): array
    {
        $raw = (string) ($this->row($vendorId)['campaigns'] ?? '');
        return $raw === '' ? [] : array_values(array_filter(explode(',', $raw)));
    }

    /** @param list<string> $ids */
    public function saveCampaigns(int $vendorId, array $ids): void
    {
        $this->update($vendorId, ['campaigns' => mb_substr(implode(',', array_unique(array_map('sanitize_key', $ids))), 0, 255)]);
    }

    /** Vendor id whose premium page is $pageId, or 0. */
    public function vendorForPremiumPage(int $pageId): int
    {
        global $wpdb;
        $table = Installer::tables()['vendors'];
        return (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE premium_page_id = %d AND premium_status = %s LIMIT 1", $pageId, self::PREMIUM_ACTIVE));
    }

    /** @return array<string, mixed> */
    private function row(int $vendorId): array
    {
        if (!isset($this->cache[$vendorId])) {
            global $wpdb;
            $table = Installer::tables()['vendors'];
            $row = $wpdb->get_row($wpdb->prepare("SELECT bank_details, premium_page_id, premium_status, campaigns FROM {$table} WHERE id = %d", $vendorId), ARRAY_A);
            $this->cache[$vendorId] = is_array($row) ? $row : [];
        }
        return $this->cache[$vendorId];
    }

    /** @param array<string, mixed> $data */
    private function update(int $vendorId, array $data): void
    {
        global $wpdb;
        $wpdb->update(Installer::tables()['vendors'], $data, ['id' => $vendorId]);
        unset($this->cache[$vendorId]);
    }
}
