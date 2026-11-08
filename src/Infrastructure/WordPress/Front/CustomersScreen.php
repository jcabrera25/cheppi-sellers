<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Front;

use Cheppi\Sellers\Application\OrderFilter;
use Cheppi\Sellers\Infrastructure\WordPress\Catalog\ProductListQuery;
use Cheppi\Sellers\Infrastructure\WordPress\Customers\WpCustomers;
use Cheppi\Sellers\Infrastructure\WordPress\Orders\OrdersReport;

/**
 * Variables of the customers list and customer detail, shared by the seller panel (scoped to the store)
 * and the management panel (every store, or one chosen in the filter).
 */
final class CustomersScreen
{
    /**
     * @param array<string, mixed> $query $_GET
     * @return array<string, mixed>
     */
    public static function listVars(WpCustomers $customers, array $query, ?int $vendorId, bool $manage): array
    {
        $query = wp_unslash($query);
        $search = sanitize_text_field((string) ($query['buscar'] ?? ''));
        $sort = sanitize_key((string) ($query['orden'] ?? ''));
        $sort = in_array($sort, WpCustomers::SORTS, true) ? $sort : 'reciente';
        $perPage = absint($query['por_pagina'] ?? 20);
        $perPage = in_array($perPage, ProductListQuery::PER_PAGE_OPTIONS, true) ? $perPage : 20;
        $storeId = $manage ? absint($query['tienda'] ?? 0) : 0;
        $page = max(1, absint($query['pagina'] ?? 1));
        $result = $customers->list($manage ? ($storeId ?: null) : $vendorId, $search, $sort, $perPage, ($page - 1) * $perPage);
        return [
            'customers' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'pages' => max(1, (int) ceil($result['total'] / $perPage)),
            'perPage' => $perPage,
            'search' => $search,
            'sort' => $sort,
            'storeId' => $storeId,
            'manage' => $manage,
            'stores' => [],
            'baseUrl' => $manage ? Routes::manageUrl('clientes') : Routes::customersUrl(),
        ];
    }

    /**
     * @param array<string, mixed> $query $_GET
     * @return array<string, mixed>|null null when the customer does not exist (or never bought from the store)
     */
    public static function detailVars(WpCustomers $customers, OrdersReport $report, string $key, array $query, ?int $vendorId, bool $manage, int $perPage = 20): ?array
    {
        if (!OrderFilter::isCustomerKey($key)) {
            return null;
        }
        $customer = $customers->find($key, $manage ? null : $vendorId);
        if ($customer === null) {
            return null;
        }
        $query = array_map(static fn ($v): string => sanitize_text_field((string) $v), (array) wp_unslash($query));
        $filter = OrderFilter::fromQuery(['cliente' => $key] + $query, $manage ? null : $vendorId, wp_timezone());
        return $report->screen($filter, absint($query['pagina'] ?? 1), $perPage) + [
            'customer' => $customer,
            'manage' => $manage,
            'stores' => [],
            'baseUrl' => $manage ? Routes::manageCustomerUrl($key) : Routes::customerUrl($key),
            'listUrl' => $manage ? Routes::manageUrl('clientes') : Routes::customersUrl(),
        ];
    }
}
