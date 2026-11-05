<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Admin;

use Cheppi\Sellers\Domain\Rules\BusinessRules;
use Cheppi\Sellers\Domain\Rules\Percent;
use InvalidArgumentException;

/**
 * Translates between the admin form (percentages people type) and the canonical rules array (basis points).
 * Pure PHP: no WordPress calls, so it is unit-tested.
 */
final class RulesFormMapper
{
    /**
     * @param array<string, mixed> $post already unslashed form input
     * @return array<string, mixed> canonical array for BusinessRules::fromArray
     */
    public static function fromForm(array $post): array
    {
        $plans = [];
        foreach ((array) ($post['plans'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = trim((string) ($row['id'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            if ($id === '' && $name === '') {
                continue; // empty row left by the "add plan" button
            }
            $limit = trim((string) ($row['product_limit'] ?? ''));
            $plans[] = [
                'id' => $id,
                'name' => $name,
                'monthly_fee' => self::decimal($row['monthly_fee'] ?? ''),
                'product_limit' => $limit === '' ? null : $limit,
                'commission_bps' => self::bps($row['commission_percent'] ?? ''),
            ];
        }

        $categories = [];
        foreach ((array) ($post['categories'] ?? []) as $row) {
            if (!is_array($row) || trim((string) ($row['term_id'] ?? '')) === '') {
                continue;
            }
            $categories[(string) $row['term_id']] = self::bps($row['commission_percent'] ?? '');
        }

        $days = array_filter(
            array_map('trim', explode(',', (string) ($post['payout_days'] ?? ''))),
            static fn (string $d): bool => $d !== ''
        );

        $brand = (array) ($post['brand_page'] ?? []);

        return [
            'currency' => 'PEN',
            'default_commission_bps' => self::bps($post['default_commission_percent'] ?? ''),
            'plans' => $plans,
            'default_plan_id' => trim((string) ($post['default_plan_id'] ?? '')),
            'category_commissions_bps' => $categories,
            'dispatch_hours' => trim((string) ($post['dispatch_hours'] ?? '')),
            'return_days' => trim((string) ($post['return_days'] ?? '')),
            'payout_days' => array_values($days),
            'minimum_payout' => self::decimal($post['minimum_payout'] ?? ''),
            'moderate_products' => !empty($post['moderate_products']),
            'brand_page' => [
                'enabled' => !empty($brand['enabled']),
                'design_fee' => self::decimal($brand['design_fee'] ?? ''),
                'renewal_fee' => self::decimal($brand['renewal_fee'] ?? '0'),
                'renewal_period' => (string) ($brand['renewal_period'] ?? 'none'),
                'revision_rounds' => trim((string) ($brand['revision_rounds'] ?? '')),
                'delivery_days' => trim((string) ($brand['delivery_days'] ?? '')),
            ],
        ];
    }

    /** @return array<string, mixed> values to pre-fill the form */
    public static function toForm(BusinessRules $rules): array
    {
        $data = $rules->toArray();
        return [
            'default_commission_percent' => Percent::fromBps($rules->defaultCommissionBps),
            'plans' => array_map(static fn (array $p): array => [
                'id' => $p['id'],
                'name' => $p['name'],
                'monthly_fee' => $p['monthly_fee'],
                'product_limit' => $p['product_limit'] === null ? '' : (string) $p['product_limit'],
                'commission_percent' => Percent::fromBps((int) $p['commission_bps']),
            ], $data['plans']),
            'default_plan_id' => $rules->defaultPlanId,
            'categories' => array_map(
                static fn (int $termId, int $bps): array => ['term_id' => (string) $termId, 'commission_percent' => Percent::fromBps($bps)],
                array_keys($rules->categoryCommissionsBps),
                array_values($rules->categoryCommissionsBps)
            ),
            'dispatch_hours' => (string) $rules->dispatchHours,
            'return_days' => (string) $rules->returnDays,
            'payout_days' => implode(', ', $rules->payoutDays),
            'minimum_payout' => $data['minimum_payout'],
            'moderate_products' => $rules->moderateNewProducts,
            'brand_page' => $data['brand_page'],
        ];
    }

    /** Invalid input is passed through as-is so the domain reports a clear error for that field. */
    private static function bps(mixed $percent): mixed
    {
        try {
            return Percent::toBps(is_scalar($percent) ? (string) $percent : '');
        } catch (InvalidArgumentException) {
            return $percent;
        }
    }

    private static function decimal(mixed $value): string
    {
        return str_replace(',', '.', trim(is_scalar($value) ? (string) $value : ''));
    }
}
