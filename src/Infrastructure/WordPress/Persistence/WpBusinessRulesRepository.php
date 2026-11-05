<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Persistence;

use Cheppi\Sellers\Application\BusinessRulesRepository;
use Cheppi\Sellers\Domain\Rules\BusinessRules;
use Cheppi\Sellers\Domain\Rules\RulesValidationException;
use Cheppi\Sellers\Infrastructure\WordPress\Installer;

/**
 * Stores the current rules in one autoloaded option (read on most requests, so no extra query)
 * and every change in the history table.
 */
final class WpBusinessRulesRepository implements BusinessRulesRepository
{
    public const OPTION = 'cheppi_business_rules';

    private ?BusinessRules $cache = null;

    public function current(): BusinessRules
    {
        if ($this->cache !== null) {
            return $this->cache;
        }
        $stored = get_option(self::OPTION);
        try {
            $this->cache = is_array($stored) ? BusinessRules::fromArray($stored) : BusinessRules::defaults();
        } catch (RulesValidationException) {
            // Never break the store because of a bad stored value; fall back to defaults.
            $this->cache = BusinessRules::defaults();
        }
        return $this->cache;
    }

    public function save(BusinessRules $rules, int $changedBy): void
    {
        global $wpdb;
        $data = $rules->toArray();
        update_option(self::OPTION, $data, true);
        $wpdb->insert(
            Installer::tables()['rules_history'],
            [
                'changed_by' => $changedBy,
                'changed_at' => current_time('mysql', true),
                'rules' => wp_json_encode($data),
            ],
            ['%d', '%s', '%s']
        );
        $this->cache = $rules;
    }

    public function history(int $limit = 20): array
    {
        global $wpdb;
        $table = Installer::tables()['rules_history'];
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT changed_at, changed_by, rules FROM {$table} ORDER BY id DESC LIMIT %d", max(1, $limit)),
            ARRAY_A
        );
        $out = [];
        foreach ((array) $rows as $row) {
            $decoded = json_decode((string) $row['rules'], true);
            $out[] = [
                'changed_at' => (string) $row['changed_at'],
                'changed_by' => (int) $row['changed_by'],
                'rules' => is_array($decoded) ? $decoded : [],
            ];
        }
        return $out;
    }
}
