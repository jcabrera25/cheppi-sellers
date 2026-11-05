<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Vendor;

use InvalidArgumentException;

/**
 * Peruvian taxpayer number (RUC): 11 digits, a valid type prefix and a module-11 check digit.
 * Only the format is verified here; whether it is "activo y habido" needs SUNAT (future integration).
 */
final class Ruc
{
    /** 10 = persona natural, 15/16/17 = otros, 20 = persona jurídica */
    private const PREFIXES = ['10', '15', '16', '17', '20'];
    private const WEIGHTS = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];

    private function __construct(public readonly string $value)
    {
    }

    public static function fromString(string $raw): self
    {
        $digits = preg_replace('/\D/', '', $raw) ?? '';
        if (!self::isValid($digits)) {
            throw new InvalidArgumentException('El RUC no es válido. Revisa los 11 dígitos.');
        }
        return new self($digits);
    }

    public static function isValid(string $digits): bool
    {
        if (!preg_match('/^\d{11}$/', $digits) || !in_array(substr($digits, 0, 2), self::PREFIXES, true)) {
            return false;
        }
        $sum = 0;
        foreach (self::WEIGHTS as $i => $weight) {
            $sum += (int) $digits[$i] * $weight;
        }
        $check = 11 - ($sum % 11);
        if ($check === 10) {
            $check = 0;
        } elseif ($check === 11) {
            $check = 1;
        }
        return $check === (int) $digits[10];
    }

    public function isNaturalPerson(): bool
    {
        return str_starts_with($this->value, '10');
    }
}
