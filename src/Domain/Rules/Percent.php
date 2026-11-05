<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Rules;

use InvalidArgumentException;

/**
 * Converts human percentages ("8", "8.5", "8,5") to basis points (800, 850) and back.
 */
final class Percent
{
    public static function toBps(string|int|float $percent): int
    {
        $value = str_replace(',', '.', trim((string) $percent));
        if ($value === '' || !is_numeric($value)) {
            throw new InvalidArgumentException('Porcentaje no válido.');
        }
        return (int) round(((float) $value) * 100);
    }

    public static function fromBps(int $bps): string
    {
        $formatted = number_format($bps / 100, 2, '.', '');
        return rtrim(rtrim($formatted, '0'), '.');
    }
}
