<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Promotion;

use DateTimeImmutable;
use DateTimeZone;

/** A vendor's discount code. It only discounts that vendor's products; the vendor pays the discount. */
final class CouponDraft
{
    public const TYPES = ['percent' => 'Porcentaje', 'fixed_product' => 'Monto por producto'];

    public function __construct(
        public readonly string $code,
        public readonly string $type,
        public readonly string $amount,
        public readonly ?string $minimumSpend,
        public readonly ?DateTimeImmutable $expiresOn,
        public readonly ?int $usageLimit
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @throws PromotionValidationException
     */
    public static function fromInput(array $input, DateTimeZone $tz, DateTimeImmutable $today): self
    {
        $errors = [];
        $code = strtoupper(trim((string) ($input['code'] ?? '')));
        if (!preg_match('/^[A-Z0-9_-]{4,20}$/', $code)) {
            $errors['code'] = 'De 4 a 20 letras o números, sin espacios.';
        }
        $type = (string) ($input['type'] ?? '');
        if (!isset(self::TYPES[$type])) {
            $errors['type'] = 'Elige el tipo de descuento.';
        }
        $amount = str_replace(',', '.', trim((string) ($input['amount'] ?? '')));
        if (!preg_match('/^\d{1,5}(\.\d{1,2})?$/', $amount) || (float) $amount <= 0 || ($type === 'percent' && (float) $amount > 90)) {
            $errors['amount'] = $type === 'percent' ? 'Porcentaje entre 1 y 90.' : 'Monto mayor a 0.';
        }
        $minimum = str_replace(',', '.', trim((string) ($input['minimum'] ?? '')));
        if ($minimum !== '' && !preg_match('/^\d{1,5}(\.\d{1,2})?$/', $minimum)) {
            $errors['minimum'] = 'Monto no válido.';
        }
        $expires = null;
        if (trim((string) ($input['expires'] ?? '')) !== '') {
            $expires = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $input['expires'], $tz) ?: null;
            if ($expires === null || $expires->format('Y-m-d') < $today->format('Y-m-d')) {
                $errors['expires'] = 'Elige una fecha de hoy en adelante.';
            }
        }
        $limit = null;
        if (trim((string) ($input['limit'] ?? '')) !== '') {
            $limit = filter_var($input['limit'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]) ?: null;
            if ($limit === null) {
                $errors['limit'] = 'Número entero mayor a 0.';
            }
        }
        if ($errors !== []) {
            throw new PromotionValidationException($errors);
        }
        return new self($code, $type, $amount, $minimum !== '' ? $minimum : null, $expires, $limit);
    }
}
