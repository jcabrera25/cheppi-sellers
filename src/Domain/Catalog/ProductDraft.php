<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Catalog;

use Cheppi\Sellers\Domain\Money\Money;
use InvalidArgumentException;

/**
 * A vendor product as typed in the panel: simple (one price) or variable (one attribute, several options).
 * Validates everything at once and reports per-field messages.
 */
final class ProductDraft
{
    public const TYPE_SIMPLE = 'simple';
    public const TYPE_VARIABLE = 'variable';
    public const MAX_VARIATIONS = 30;

    /** @param list<VariationDraft> $variations */
    private function __construct(
        public readonly string $type,
        public readonly string $name,
        public readonly string $description,
        public readonly int $categoryId,
        public readonly ?Money $price,
        public readonly ?Money $salePrice,
        public readonly string $sku,
        public readonly ?int $stock,
        public readonly string $attributeName,
        public readonly array $variations
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @param list<int> $validCategoryIds
     * @throws ProductValidationException
     */
    public static function fromArray(array $input, array $validCategoryIds): self
    {
        $errors = [];
        $text = static fn (string $key): string => trim((string) ($input[$key] ?? ''));

        $type = $text('type') === self::TYPE_VARIABLE ? self::TYPE_VARIABLE : self::TYPE_SIMPLE;

        $name = $text('name');
        if (mb_strlen($name) < 3 || mb_strlen($name) > 120) {
            $errors['name'] = 'El nombre debe tener entre 3 y 120 caracteres.';
        }
        $description = $text('description');
        if (mb_strlen($description) > 5000) {
            $errors['description'] = 'La descripción puede tener hasta 5000 caracteres.';
        }
        $categoryId = (int) $text('category');
        if (!in_array($categoryId, $validCategoryIds, true)) {
            $errors['category'] = 'Elige una categoría.';
        }

        $price = $sale = null;
        $sku = $text('sku');
        $stock = null;
        $attributeName = '';
        $variations = [];

        if ($type === self::TYPE_SIMPLE) {
            [$price, $sale] = self::prices($text('price'), $text('sale_price'), 'price', 'sale_price', $errors);
            if ($sku !== '' && !self::validSku($sku)) {
                $errors['sku'] = 'El código solo admite letras, números, punto, guion y guion bajo.';
            }
            if ($text('stock') !== '') {
                $stock = self::stock($text('stock'), 'stock', $errors);
            }
        } else {
            $sku = '';
            $attributeName = $text('attribute_name');
            if (mb_strlen($attributeName) < 2 || mb_strlen($attributeName) > 30) {
                $errors['attribute_name'] = 'Escribe qué cambia entre opciones, por ejemplo «Tono» o «Tamaño».';
            }
            $rows = array_values(array_filter(
                is_array($input['variations'] ?? null) ? $input['variations'] : [],
                static fn ($row): bool => is_array($row) && trim((string) ($row['option'] ?? '')) !== ''
            ));
            if ($rows === []) {
                $errors['variations'] = 'Agrega al menos una opción.';
            } elseif (count($rows) > self::MAX_VARIATIONS) {
                $errors['variations'] = sprintf('Máximo %d opciones por producto.', self::MAX_VARIATIONS);
            }
            $seen = [];
            foreach (array_slice($rows, 0, self::MAX_VARIATIONS) as $i => $row) {
                $option = trim((string) $row['option']);
                $key = mb_strtolower($option);
                if (isset($seen[$key])) {
                    $errors["variations.$i.option"] = "La opción «{$option}» está repetida.";
                }
                $seen[$key] = true;
                if (mb_strlen($option) > 60) {
                    $errors["variations.$i.option"] = 'Cada opción puede tener hasta 60 caracteres.';
                }
                [$vPrice, $vSale] = self::prices(
                    trim((string) ($row['price'] ?? '')),
                    trim((string) ($row['sale_price'] ?? '')),
                    "variations.$i.price",
                    "variations.$i.sale_price",
                    $errors
                );
                $vSku = trim((string) ($row['sku'] ?? ''));
                if ($vSku !== '' && !self::validSku($vSku)) {
                    $errors["variations.$i.sku"] = 'Código no válido.';
                }
                $vStock = self::stock(trim((string) ($row['stock'] ?? '0')) ?: '0', "variations.$i.stock", $errors);
                $id = isset($row['id']) && (int) $row['id'] > 0 ? (int) $row['id'] : null;
                if ($vPrice !== null) {
                    $variations[] = new VariationDraft($option, $vPrice, $vSale, $vSku, $vStock, $id);
                }
            }
        }

        if ($errors !== []) {
            throw new ProductValidationException($errors);
        }
        return new self($type, $name, $description, $categoryId, $price, $sale, $sku, $stock, $attributeName, $variations);
    }

    public function isVariable(): bool
    {
        return $this->type === self::TYPE_VARIABLE;
    }

    /**
     * @param array<string, string> $errors
     * @return array{0: ?Money, 1: ?Money}
     */
    private static function prices(string $price, string $sale, string $priceField, string $saleField, array &$errors): array
    {
        $regular = self::money($price);
        if ($regular === null || $regular->cents <= 0) {
            $errors[$priceField] = 'Escribe un precio mayor a 0.';
            return [null, null];
        }
        if ($sale === '') {
            return [$regular, null];
        }
        $offer = self::money($sale);
        if ($offer === null || $offer->cents <= 0 || $offer->cents >= $regular->cents) {
            $errors[$saleField] = 'El precio de oferta debe ser menor al precio normal.';
            return [$regular, null];
        }
        return [$regular, $offer];
    }

    private static function money(string $value): ?Money
    {
        try {
            return Money::ofDecimal(str_replace(',', '.', $value));
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /** @param array<string, string> $errors */
    private static function stock(string $value, string $field, array &$errors): int
    {
        $stock = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100000]]);
        if ($stock === false) {
            $errors[$field] = 'El stock debe ser un número entero desde 0.';
            return 0;
        }
        return $stock;
    }

    private static function validSku(string $sku): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9._-]{1,40}$/', $sku);
    }
}
