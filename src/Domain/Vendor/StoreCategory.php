<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Vendor;

/**
 * Main line of business a vendor declares when applying.
 */
final class StoreCategory
{
    public const ALL = [
        'maquillaje' => 'Maquillaje',
        'cuidado-piel' => 'Cuidado de la piel',
        'cuidado-cabello' => 'Cuidado del cabello',
        'fragancias' => 'Fragancias',
        'cuidado-personal' => 'Cuidado personal',
        'unas' => 'Uñas',
        'otros' => 'Otros',
    ];

    public static function isValid(string $code): bool
    {
        return isset(self::ALL[$code]);
    }

    public static function label(string $code): string
    {
        return self::ALL[$code] ?? $code;
    }
}
