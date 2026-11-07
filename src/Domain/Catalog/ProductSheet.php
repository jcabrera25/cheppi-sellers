<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Catalog;

/**
 * The spreadsheet format for bulk products (CSV from Excel or Google Sheets).
 * Columns are matched by header name, in any order, with or without accents.
 */
final class ProductSheet
{
    public const MAX_ROWS = 100;

    /** Canonical column => accepted header names. */
    public const COLUMNS = [
        'sku' => ['sku', 'codigo'],
        'name' => ['nombre', 'producto', 'name'],
        'description' => ['descripcion', 'description'],
        'category' => ['categoria', 'category'],
        'price' => ['precio', 'price'],
        'sale_price' => ['precio oferta', 'precio_oferta', 'oferta', 'sale price'],
        'stock' => ['stock', 'cantidad'],
        'image_url' => ['imagen', 'imagen url', 'imagen_url', 'foto', 'image'],
    ];

    /** Header row for the template and the export. */
    public const HEADERS = ['sku', 'nombre', 'descripcion', 'categoria', 'precio', 'precio_oferta', 'stock', 'imagen_url'];

    /**
     * @return array{rows: list<array{line: int, data: array<string, string>}>, errors: list<string>}
     */
    public static function parse(string $content): array
    {
        $content = (string) preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = preg_split('/\r\n|\n|\r/', trim($content)) ?: [];
        if (count($lines) < 2) {
            return ['rows' => [], 'errors' => ['El archivo no tiene filas de productos.']];
        }
        $delimiter = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : (substr_count($lines[0], "\t") > substr_count($lines[0], ',') ? "\t" : ',');
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, implode("\n", $lines));
        rewind($handle);

        $header = array_map([self::class, 'normalize'], (array) fgetcsv($handle, 0, $delimiter, '"', '\\'));
        $map = [];
        foreach (self::COLUMNS as $key => $names) {
            foreach ($header as $index => $name) {
                if (in_array($name, $names, true)) {
                    $map[$key] = $index;
                    break;
                }
            }
        }
        $errors = [];
        foreach (['name', 'price'] as $required) {
            if (!isset($map[$required])) {
                $errors[] = sprintf('Falta la columna «%s».', $required === 'name' ? 'nombre' : 'precio');
            }
        }
        $rows = [];
        $line = 1;
        while ($errors === [] && ($cells = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            $line++;
            if ($cells === [null] || implode('', array_map('strval', $cells)) === '') {
                continue;
            }
            if (count($rows) >= self::MAX_ROWS) {
                $errors[] = sprintf('Máximo %d productos por archivo; se leyeron los primeros.', self::MAX_ROWS);
                break;
            }
            $data = [];
            foreach ($map as $key => $index) {
                $data[$key] = trim((string) ($cells[$index] ?? ''));
            }
            $rows[] = ['line' => $line, 'data' => $data];
        }
        fclose($handle);
        return ['rows' => $rows, 'errors' => $errors];
    }

    /** "Precio Oferta " → "precio oferta", "Categoría" → "categoria". */
    public static function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = strtr($text, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);
        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    /**
     * Turns a sheet row into product form input (simple product), resolving the category by name.
     *
     * @param array<string, string> $data
     * @param array<int, string>    $categories id => name
     * @return array<string, mixed>
     */
    public static function toInput(array $data, array $categories): array
    {
        $category = '';
        $wanted = self::normalize($data['category'] ?? '');
        foreach ($categories as $id => $name) {
            if ($wanted !== '' && (self::normalize($name) === $wanted || (string) $id === $wanted)) {
                $category = (string) $id;
                break;
            }
        }
        return [
            'type' => 'simple',
            'name' => $data['name'] ?? '',
            'description' => $data['description'] ?? '',
            'category' => $category,
            'price' => str_replace(',', '.', $data['price'] ?? ''),
            'sale_price' => str_replace(',', '.', $data['sale_price'] ?? ''),
            'sku' => $data['sku'] ?? '',
            'stock' => $data['stock'] ?? '',
            'attribute_name' => '',
            'variations' => [],
        ];
    }
}
