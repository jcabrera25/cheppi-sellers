<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Catalog;

/**
 * Extra filters of the product lists (seller and management panel): category, product type and how
 * many to show per page. Read from the query string and turned into WP_Query arguments.
 */
final class ProductListQuery
{
    public const PER_PAGE_OPTIONS = [10, 20, 50, 100];
    public const TYPES = ['simple', 'variable'];

    public function __construct(
        public readonly int $categoryId = 0,
        public readonly string $type = '',
        public readonly int $perPage = 20
    ) {
    }

    /** @param array<string, mixed> $query */
    public static function fromQuery(array $query): self
    {
        $type = sanitize_key((string) ($query['tipo'] ?? ''));
        $perPage = absint($query['por_pagina'] ?? 20);
        return new self(
            absint($query['categoria'] ?? 0),
            in_array($type, self::TYPES, true) ? $type : '',
            in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 20
        );
    }

    /** @return array<string, string> the filters as query args (defaults left out) */
    public function toQuery(): array
    {
        return array_filter([
            'categoria' => $this->categoryId > 0 ? (string) $this->categoryId : '',
            'tipo' => $this->type,
            'por_pagina' => $this->perPage !== 20 ? (string) $this->perPage : '',
        ]);
    }

    public function isFiltered(): bool
    {
        return $this->categoryId > 0 || $this->type !== '';
    }

    /** @return list<array<string, mixed>> */
    public function taxQuery(): array
    {
        $tax = [];
        if ($this->categoryId > 0) {
            $tax[] = ['taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => [$this->categoryId], 'include_children' => true];
        }
        if ($this->type !== '') {
            $tax[] = ['taxonomy' => 'product_type', 'field' => 'slug', 'terms' => [$this->type]];
        }
        return $tax;
    }

    /** @return array<string, string> */
    public static function typeLabels(): array
    {
        return ['simple' => __('Simple', 'cheppi-sellers'), 'variable' => __('Variable', 'cheppi-sellers')];
    }
}
