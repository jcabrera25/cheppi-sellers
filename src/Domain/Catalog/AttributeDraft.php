<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Catalog;

/** A product attribute ("Tono": Rojo, Nude). Visible in the product page and/or used to build variations. */
final class AttributeDraft
{
    /** @param list<string> $options */
    public function __construct(
        public readonly string $name,
        public readonly array $options,
        public readonly bool $visible,
        public readonly bool $forVariations
    ) {
    }

    /** "Rojo | Nude, Coral" → ["Rojo", "Nude", "Coral"] (unique, case-insensitive). @return list<string> */
    public static function splitOptions(string $raw): array
    {
        $out = [];
        foreach (preg_split('/\s*[|,\n]\s*/', trim($raw)) ?: [] as $option) {
            $option = trim($option);
            if ($option !== '' && !in_array(mb_strtolower($option), array_map('mb_strtolower', $out), true)) {
                $out[] = $option;
            }
        }
        return $out;
    }
}
