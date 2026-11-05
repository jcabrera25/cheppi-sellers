<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Vendor;

/**
 * URL-safe store handle for /tienda/<slug>, built from the store name.
 */
final class StoreSlug
{
    public static function fromName(string $name): string
    {
        $map = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u', 'ç' => 'c', '&' => ' y ',
        ];
        $slug = strtr(mb_strtolower(trim($name)), $map);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim(substr(trim($slug, '-'), 0, 60), '-');
        return $slug === '' ? 'tienda' : $slug;
    }

    /**
     * Appends -2, -3… until $exists says the slug is free.
     *
     * @param callable(string): bool $exists
     */
    public static function unique(string $name, callable $exists): string
    {
        $base = self::fromName($name);
        $slug = $base;
        for ($n = 2; $exists($slug); $n++) {
            $slug = $base . '-' . $n;
        }
        return $slug;
    }
}
