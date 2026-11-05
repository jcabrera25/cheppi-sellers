<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Rules;

use Cheppi\Sellers\Domain\Money\Money;
use InvalidArgumentException;

/**
 * Every commercial value of the marketplace, editable by the admin without code.
 *
 * Canonical array format (what is persisted): rates in basis points (800 = 8%),
 * money as decimal strings ("49.00"), plain ints for days/hours.
 */
final class BusinessRules
{
    public const MAX_BPS = 10000;

    /**
     * @param list<Plan>        $plans
     * @param array<int, int>   $categoryCommissionsBps category term id => bps
     * @param list<int>         $payoutDays days of month (1–31; 31 = last day of month)
     */
    public function __construct(
        public readonly string $currency,
        public readonly int $defaultCommissionBps,
        public readonly array $plans,
        public readonly string $defaultPlanId,
        public readonly array $categoryCommissionsBps,
        public readonly int $dispatchHours,
        public readonly int $returnDays,
        public readonly array $payoutDays,
        public readonly Money $minimumPayout,
        public readonly BrandPageService $brandPage
    ) {
    }

    /** Example values so the marketplace works before final numbers are decided. */
    public static function defaults(): self
    {
        return self::fromArray([
            'currency' => 'PEN',
            'default_commission_bps' => 800,
            'plans' => [
                ['id' => 'emprende', 'name' => 'Emprende', 'monthly_fee' => '0.00', 'product_limit' => 50, 'commission_bps' => 800],
                ['id' => 'pro', 'name' => 'Pro', 'monthly_fee' => '49.00', 'product_limit' => null, 'commission_bps' => 500],
            ],
            'default_plan_id' => 'emprende',
            'category_commissions_bps' => [],
            'dispatch_hours' => 48,
            'return_days' => 7,
            'payout_days' => [15, 31],
            'minimum_payout' => '20.00',
            'brand_page' => [
                'enabled' => true,
                'design_fee' => '350.00',
                'renewal_fee' => '0.00',
                'renewal_period' => BrandPageService::RENEWAL_NONE,
                'revision_rounds' => 2,
                'delivery_days' => 10,
            ],
        ]);
    }

    /**
     * Validates and builds rules. Collects every error before failing.
     *
     * @param array<string, mixed> $data
     * @throws RulesValidationException
     */
    public static function fromArray(array $data): self
    {
        $errors = [];
        $currency = strtoupper((string) ($data['currency'] ?? 'PEN'));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            $errors['currency'] = 'Moneda no válida.';
            $currency = 'PEN';
        }

        $defaultBps = self::bps($data['default_commission_bps'] ?? null, 'default_commission_bps', $errors);

        $plans = [];
        $planIds = [];
        $rawPlans = is_array($data['plans'] ?? null) ? $data['plans'] : [];
        if ($rawPlans === []) {
            $errors['plans'] = 'Debe existir al menos un plan.';
        }
        foreach (array_values($rawPlans) as $i => $raw) {
            $key = "plans.$i";
            if (!is_array($raw)) {
                $errors[$key] = 'Plan no válido.';
                continue;
            }
            $id = strtolower(trim((string) ($raw['id'] ?? '')));
            if (!preg_match('/^[a-z0-9_-]{2,32}$/', $id)) {
                $errors["$key.id"] = 'El código del plan debe tener 2–32 letras, números o guiones.';
            } elseif (in_array($id, $planIds, true)) {
                $errors["$key.id"] = 'Código de plan repetido.';
            }
            $planIds[] = $id;
            $name = trim((string) ($raw['name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 60) {
                $errors["$key.name"] = 'El nombre del plan es obligatorio (máx. 60 caracteres).';
            }
            $fee = self::money($raw['monthly_fee'] ?? null, $currency, "$key.monthly_fee", $errors);
            $limitRaw = $raw['product_limit'] ?? null;
            $limit = null;
            if ($limitRaw !== null && $limitRaw !== '') {
                $limit = filter_var($limitRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($limit === false) {
                    $errors["$key.product_limit"] = 'El límite de productos debe ser un entero positivo o vacío (ilimitado).';
                    $limit = null;
                }
            }
            $bps = self::bps($raw['commission_bps'] ?? null, "$key.commission_bps", $errors);
            $plans[] = new Plan($id, $name, $fee, $limit, $bps);
        }

        $defaultPlanId = (string) ($data['default_plan_id'] ?? ($planIds[0] ?? ''));
        if (!in_array($defaultPlanId, $planIds, true)) {
            $errors['default_plan_id'] = 'El plan por defecto debe existir.';
        }

        $categories = [];
        foreach ((array) ($data['category_commissions_bps'] ?? []) as $termId => $bps) {
            $id = filter_var($termId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) {
                $errors["category_commissions_bps.$termId"] = 'Categoría no válida.';
                continue;
            }
            $categories[$id] = self::bps($bps, "category_commissions_bps.$termId", $errors);
        }

        $dispatchHours = self::int($data['dispatch_hours'] ?? null, 1, 720, 'dispatch_hours', $errors);
        $returnDays = self::int($data['return_days'] ?? null, 0, 90, 'return_days', $errors);

        $payoutDays = [];
        foreach ((array) ($data['payout_days'] ?? []) as $day) {
            $d = filter_var($day, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 31]]);
            if ($d === false) {
                $errors['payout_days'] = 'Los días de corte van del 1 al 31.';
                continue;
            }
            $payoutDays[] = $d;
        }
        $payoutDays = array_values(array_unique($payoutDays));
        sort($payoutDays);
        if ($payoutDays === [] && !isset($errors['payout_days'])) {
            $errors['payout_days'] = 'Define al menos un día de corte.';
        }

        $minimumPayout = self::money($data['minimum_payout'] ?? null, $currency, 'minimum_payout', $errors);

        $bp = is_array($data['brand_page'] ?? null) ? $data['brand_page'] : [];
        $period = (string) ($bp['renewal_period'] ?? BrandPageService::RENEWAL_NONE);
        if (!in_array($period, BrandPageService::RENEWAL_PERIODS, true)) {
            $errors['brand_page.renewal_period'] = 'Periodo de renovación no válido.';
            $period = BrandPageService::RENEWAL_NONE;
        }
        $brandPage = new BrandPageService(
            (bool) ($bp['enabled'] ?? false),
            self::money($bp['design_fee'] ?? null, $currency, 'brand_page.design_fee', $errors),
            self::money($bp['renewal_fee'] ?? '0', $currency, 'brand_page.renewal_fee', $errors),
            $period,
            self::int($bp['revision_rounds'] ?? null, 0, 20, 'brand_page.revision_rounds', $errors),
            self::int($bp['delivery_days'] ?? null, 1, 120, 'brand_page.delivery_days', $errors)
        );

        if ($errors !== []) {
            throw new RulesValidationException($errors);
        }

        return new self(
            $currency,
            $defaultBps,
            $plans,
            $defaultPlanId,
            $categories,
            $dispatchHours,
            $returnDays,
            $payoutDays,
            $minimumPayout,
            $brandPage
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'currency' => $this->currency,
            'default_commission_bps' => $this->defaultCommissionBps,
            'plans' => array_map(static fn (Plan $p): array => $p->toArray(), $this->plans),
            'default_plan_id' => $this->defaultPlanId,
            'category_commissions_bps' => $this->categoryCommissionsBps,
            'dispatch_hours' => $this->dispatchHours,
            'return_days' => $this->returnDays,
            'payout_days' => $this->payoutDays,
            'minimum_payout' => $this->minimumPayout->toDecimal(),
            'brand_page' => $this->brandPage->toArray(),
        ];
    }

    public function plan(string $id): ?Plan
    {
        foreach ($this->plans as $plan) {
            if ($plan->id === $id) {
                return $plan;
            }
        }
        return null;
    }

    /** @param array<string, string> $errors */
    private static function bps(mixed $value, string $field, array &$errors): int
    {
        $bps = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => self::MAX_BPS]]);
        if ($bps === false) {
            $errors[$field] = 'La comisión debe estar entre 0% y 100%.';
            return 0;
        }
        return $bps;
    }

    /** @param array<string, string> $errors */
    private static function int(mixed $value, int $min, int $max, string $field, array &$errors): int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
        if ($int === false) {
            $errors[$field] = sprintf('Debe ser un número entre %d y %d.', $min, $max);
            return $min;
        }
        return $int;
    }

    /** @param array<string, string> $errors */
    private static function money(mixed $value, string $currency, string $field, array &$errors): Money
    {
        try {
            $money = Money::ofDecimal(is_string($value) || is_int($value) || is_float($value) ? $value : '', $currency);
        } catch (InvalidArgumentException) {
            $errors[$field] = 'Monto no válido.';
            return Money::zero($currency);
        }
        if ($money->isNegative()) {
            $errors[$field] = 'El monto no puede ser negativo.';
        }
        return $money;
    }
}
