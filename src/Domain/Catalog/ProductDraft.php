<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Catalog;

use Cheppi\Sellers\Domain\Money\Money;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * A product as typed in the panel, with the options of the WooCommerce editor that matter to a marketplace:
 * simple or variable, short and long description, several categories, brands, tags, scheduled sale,
 * inventory (stock, status, backorders, low-stock alert, one per order), shipping data, attributes,
 * variations with their own price/stock/photo, up-sells, cross-sells and a purchase note.
 * Validates everything at once and reports per-field messages.
 */
final class ProductDraft
{
    public const TYPE_SIMPLE = 'simple';
    public const TYPE_VARIABLE = 'variable';
    public const MAX_VARIATIONS = 60;
    public const MAX_ATTRIBUTES = 5;
    public const MAX_OPTIONS = 30;
    public const MAX_CATEGORIES = 5;
    public const MAX_TAGS = 10;
    public const STOCK_STATUSES = ['instock' => 'Hay existencias', 'outofstock' => 'Agotado', 'onbackorder' => 'Por encargo'];
    public const BACKORDERS = ['no' => 'No permitir', 'notify' => 'Permitir y avisar al cliente', 'yes' => 'Permitir'];

    /**
     * @param list<int>            $categoryIds
     * @param list<int>            $brandIds
     * @param list<string>         $tags
     * @param list<AttributeDraft> $attributes
     * @param list<VariationDraft> $variations
     * @param list<int>            $upsellIds
     * @param list<int>            $crossSellIds
     * @param array{weight: ?string, length: ?string, width: ?string, height: ?string} $shipping
     */
    private function __construct(
        public readonly string $type,
        public readonly string $name,
        public readonly string $shortDescription,
        public readonly string $description,
        public readonly array $categoryIds,
        public readonly array $brandIds,
        public readonly array $tags,
        public readonly ?Money $price,
        public readonly ?Money $salePrice,
        public readonly ?DateTimeImmutable $saleFrom,
        public readonly ?DateTimeImmutable $saleTo,
        public readonly string $sku,
        public readonly bool $manageStock,
        public readonly ?int $stock,
        public readonly string $stockStatus,
        public readonly string $backorders,
        public readonly ?int $lowStock,
        public readonly bool $soldIndividually,
        public readonly bool $virtual,
        public readonly array $shipping,
        public readonly array $attributes,
        public readonly array $variations,
        public readonly array $upsellIds,
        public readonly array $crossSellIds,
        public readonly string $purchaseNote
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @throws ProductValidationException
     */
    public static function fromArray(array $input, ProductChoices $choices): self
    {
        $errors = [];
        $text = static fn (string $key): string => trim((string) ($input[$key] ?? ''));
        $flag = static fn (string $key): bool => !empty($input[$key]) && $input[$key] !== '0';
        $ids = static fn (string $key): array => array_values(array_unique(array_filter(array_map('intval', (array) ($input[$key] ?? [])))));

        $type = $text('type') === self::TYPE_VARIABLE ? self::TYPE_VARIABLE : self::TYPE_SIMPLE;

        $name = $text('name');
        if (mb_strlen($name) < 3 || mb_strlen($name) > 120) {
            $errors['name'] = 'El nombre debe tener entre 3 y 120 caracteres.';
        }
        $short = $text('short_description');
        if (mb_strlen(strip_tags($short)) > 1000) {
            $errors['short_description'] = 'La descripción corta puede tener hasta 1000 caracteres.';
        }
        $description = $text('description');
        if (mb_strlen(strip_tags($description)) > 10000) {
            $errors['description'] = 'La descripción puede tener hasta 10 000 caracteres.';
        }

        $categories = array_values(array_intersect($ids('categories'), array_keys($choices->categories)));
        if ($categories === []) {
            $errors['categories'] = 'Elige al menos una categoría.';
        } elseif (count($categories) > self::MAX_CATEGORIES) {
            $errors['categories'] = sprintf('Elige hasta %d categorías.', self::MAX_CATEGORIES);
        }
        $brands = array_values(array_intersect($ids('brands'), array_keys($choices->brands)));

        $tags = AttributeDraft::splitOptions(str_replace(['|', "\n"], ',', $text('tags')));
        if (count($tags) > self::MAX_TAGS || array_filter($tags, static fn (string $t): bool => mb_strlen($t) > 30) !== []) {
            $errors['tags'] = sprintf('Hasta %d etiquetas de 30 caracteres.', self::MAX_TAGS);
        }

        // Attributes (both types; variable products need at least one "for variations").
        $attributes = [];
        $seenNames = [];
        $rows = array_values(array_filter((array) ($input['attributes'] ?? []), static fn ($r): bool => is_array($r) && trim((string) ($r['name'] ?? '')) !== ''));
        if (count($rows) > self::MAX_ATTRIBUTES) {
            $errors['attributes'] = sprintf('Máximo %d atributos.', self::MAX_ATTRIBUTES);
        }
        foreach (array_slice($rows, 0, self::MAX_ATTRIBUTES) as $i => $row) {
            $attrName = trim((string) $row['name']);
            $options = AttributeDraft::splitOptions((string) ($row['options'] ?? ''));
            $key = mb_strtolower($attrName);
            if (mb_strlen($attrName) < 2 || mb_strlen($attrName) > 30) {
                $errors["attributes.$i.name"] = 'Nombre de 2 a 30 caracteres, por ejemplo «Tono».';
            } elseif (isset($seenNames[$key])) {
                $errors["attributes.$i.name"] = 'Este atributo está repetido.';
            }
            $seenNames[$key] = true;
            if ($options === []) {
                $errors["attributes.$i.options"] = 'Escribe al menos un valor, separados por «|».';
            } elseif (count($options) > self::MAX_OPTIONS || array_filter($options, static fn (string $o): bool => mb_strlen($o) > 60) !== []) {
                $errors["attributes.$i.options"] = sprintf('Hasta %d valores de 60 caracteres.', self::MAX_OPTIONS);
            }
            $attributes[] = new AttributeDraft($attrName, $options, !isset($row['visible']) || self::flagOf($row, 'visible'), $type === self::TYPE_VARIABLE && self::flagOf($row, 'variation'));
        }

        $price = $sale = null;
        $saleFrom = $saleTo = null;
        $sku = $text('sku');
        if ($sku !== '' && !self::validSku($sku)) {
            $errors['sku'] = 'El SKU solo admite letras, números, punto, guion y guion bajo (hasta 40).';
        }
        // Variable products keep stock per variation; product-level stock would hide their quantities.
        $manageStock = $type === self::TYPE_SIMPLE && $flag('manage_stock');
        $stock = null;
        if ($manageStock) {
            $stock = self::stock($text('stock') === '' ? '0' : $text('stock'), 'stock', $errors);
        }
        $stockStatus = isset(self::STOCK_STATUSES[$text('stock_status')]) ? $text('stock_status') : 'instock';
        $backorders = isset(self::BACKORDERS[$text('backorders')]) ? $text('backorders') : 'no';
        $lowStock = $text('low_stock') !== '' ? self::stock($text('low_stock'), 'low_stock', $errors) : null;
        $virtual = $type === self::TYPE_SIMPLE && $flag('virtual');
        $shipping = [
            'weight' => self::measure($text('weight'), 'weight', 'peso (kg)', $errors),
            'length' => self::measure($text('length'), 'dimensions', 'largo', $errors),
            'width' => self::measure($text('width'), 'dimensions', 'ancho', $errors),
            'height' => self::measure($text('height'), 'dimensions', 'alto', $errors),
        ];

        $variations = [];
        if ($type === self::TYPE_SIMPLE) {
            [$price, $sale] = self::prices($text('price'), $text('sale_price'), 'price', 'sale_price', $errors);
            [$saleFrom, $saleTo] = self::schedule($text('sale_from'), $text('sale_to'), $sale !== null, $errors);
        } else {
            $variationAttributes = array_values(array_filter($attributes, static fn (AttributeDraft $a): bool => $a->forVariations));
            if ($variationAttributes === [] && !isset($errors['attributes'])) {
                $errors['attributes'] = 'Agrega al menos un atributo marcado «Usar para variaciones» (por ejemplo «Tono»).';
            }
            $variations = self::variations($input, $variationAttributes, $errors);
        }

        $linkable = array_keys($choices->linkable);
        $upsells = array_values(array_intersect($ids('upsells'), $linkable));
        $crossSells = array_values(array_intersect($ids('cross_sells'), $linkable));

        $note = $text('purchase_note');
        if (mb_strlen($note) > 500) {
            $errors['purchase_note'] = 'La nota de compra puede tener hasta 500 caracteres.';
        }

        if ($errors !== []) {
            throw new ProductValidationException($errors);
        }
        return new self(
            $type, $name, $short, $description, $categories, $brands, $tags,
            $price, $sale, $saleFrom, $saleTo, $sku,
            $manageStock, $stock, $stockStatus, $backorders, $lowStock,
            $flag('sold_individually'), $virtual, $shipping,
            $attributes, $variations, $upsells, $crossSells, $note
        );
    }

    public function isVariable(): bool
    {
        return $this->type === self::TYPE_VARIABLE;
    }

    /**
     * @param array<string, mixed>  $input
     * @param list<AttributeDraft>  $attributes those used for variations
     * @param array<string, string> $errors
     * @return list<VariationDraft>
     */
    private static function variations(array $input, array $attributes, array &$errors): array
    {
        $rows = array_values(array_filter((array) ($input['variations'] ?? []), 'is_array'));
        if ($attributes === []) {
            return [];
        }
        if ($rows === []) {
            $errors['variations'] = 'Crea las variaciones con «Generar variaciones» y ponles precio.';
            return [];
        }
        if (count($rows) > self::MAX_VARIATIONS) {
            $errors['variations'] = sprintf('Máximo %d variaciones por producto.', self::MAX_VARIATIONS);
            return [];
        }
        $out = [];
        $seen = [];
        foreach ($rows as $i => $row) {
            $combo = [];
            foreach ($attributes as $a => $attribute) {
                $value = trim((string) ($row['attributes'][$a] ?? ''));
                $match = null;
                foreach ($attribute->options as $option) {
                    if (mb_strtolower($option) === mb_strtolower($value)) {
                        $match = $option;
                    }
                }
                if ($match === null) {
                    $errors["variations.$i.attributes"] = sprintf('Elige un valor de «%s».', $attribute->name);
                    continue 2;
                }
                $combo[$attribute->name] = $match;
            }
            $key = mb_strtolower(implode('|', $combo));
            if (isset($seen[$key])) {
                $errors["variations.$i.attributes"] = sprintf('La combinación «%s» está repetida.', implode(' / ', $combo));
                continue;
            }
            $seen[$key] = true;

            $enabled = !isset($row['enabled']) || (!empty($row['enabled']) && $row['enabled'] !== '0');
            [$price, $sale] = self::prices(trim((string) ($row['price'] ?? '')), trim((string) ($row['sale_price'] ?? '')), "variations.$i.price", "variations.$i.sale_price", $errors);
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku !== '' && !self::validSku($sku)) {
                $errors["variations.$i.sku"] = 'SKU no válido.';
            }
            $manage = !isset($row['manage_stock']) || (!empty($row['manage_stock']) && $row['manage_stock'] !== '0');
            $stock = $manage ? self::stock(trim((string) ($row['stock'] ?? '')) === '' ? '0' : trim((string) $row['stock']), "variations.$i.stock", $errors) : null;
            $status = isset(self::STOCK_STATUSES[(string) ($row['stock_status'] ?? '')]) ? (string) $row['stock_status'] : 'instock';
            $weight = self::measure(trim((string) ($row['weight'] ?? '')), "variations.$i.weight", 'peso', $errors);
            $description = trim((string) ($row['description'] ?? ''));
            if (mb_strlen($description) > 500) {
                $errors["variations.$i.description"] = 'Hasta 500 caracteres.';
            }
            $id = isset($row['id']) && (int) $row['id'] > 0 ? (int) $row['id'] : null;
            if ($price !== null) {
                $out[] = new VariationDraft($combo, $enabled, $price, $sale, $sku, $manage, $stock, $status, $weight, max(0, (int) ($row['image_id'] ?? 0)), $description, $id);
            }
        }
        return $out;
    }

    /** @param array<string, mixed> $row */
    private static function flagOf(array $row, string $key): bool
    {
        return !empty($row[$key]) && $row[$key] !== '0';
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

    /**
     * @param array<string, string> $errors
     * @return array{0: ?DateTimeImmutable, 1: ?DateTimeImmutable}
     */
    private static function schedule(string $from, string $to, bool $hasSale, array &$errors): array
    {
        if ($from === '' && $to === '') {
            return [null, null];
        }
        $start = $from !== '' ? (DateTimeImmutable::createFromFormat('!Y-m-d', $from) ?: null) : null;
        $end = $to !== '' ? (DateTimeImmutable::createFromFormat('!Y-m-d', $to) ?: null) : null;
        if (!$hasSale) {
            $errors['sale_from'] = 'Para programar la oferta, escribe primero el precio de oferta.';
        } elseif (($from !== '' && $start === null) || ($to !== '' && $end === null) || ($start && $end && $end < $start)) {
            $errors['sale_from'] = 'Revisa las fechas: el fin debe ser igual o posterior al inicio.';
        }
        return [$start, $end];
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
            $errors[$field] = 'Debe ser un número entero desde 0.';
            return 0;
        }
        return $stock;
    }

    /** Weight (kg) or dimension (cm): empty, or a positive decimal. @param array<string, string> $errors */
    private static function measure(string $value, string $field, string $label, array &$errors): ?string
    {
        if ($value === '') {
            return null;
        }
        $value = str_replace(',', '.', $value);
        if (!preg_match('/^\d{1,4}(\.\d{1,3})?$/', $value) || (float) $value <= 0) {
            $errors[$field] = sprintf('Revisa el %s: número mayor a 0.', $label);
            return null;
        }
        return $value;
    }

    private static function validSku(string $sku): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9._-]{1,40}$/', $sku);
    }
}
