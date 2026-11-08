<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Orders;

use Cheppi\Sellers\Application\Fulfillments;
use Cheppi\Sellers\Application\OrderFilter;
use Cheppi\Sellers\Domain\Order\Fulfillment;
use Cheppi\Sellers\Infrastructure\WordPress\Customers\WpCustomers;
use Cheppi\Sellers\Infrastructure\WordPress\Persistence\WpLedger;
use Cheppi\Sellers\Infrastructure\WordPress\Persistence\WpVendorRepository;
use WC_Order;
use WC_Order_Item_Product;

/**
 * Rows of the orders table (screen, print, PDF and Excel share them): one row per order and store, with
 * products bought, billing and shipping address, gross sale, Cheppi commission and what the store earns.
 */
final class OrdersReport
{
    public const EXPORT_LIMIT = 2000;

    public function __construct(
        private readonly Fulfillments $fulfillments,
        private readonly WpLedger $ledger,
        private readonly WpVendorRepository $vendors,
        private readonly WpCustomers $customers
    ) {
    }

    /** A filter by customer becomes a filter by that customer's orders (looked up once). */
    public function resolve(OrderFilter $filter): OrderFilter
    {
        return $filter->customer !== null && $filter->orderIds === null
            ? $filter->withOrderIds($this->customers->orderIds($filter->customer, $filter->vendorId))
            : $filter;
    }

    /**
     * @return list<array{part: Fulfillment, order: ?WC_Order, store: string, customer: string, products: list<string>, billing: string, shipping: string, phone: string, gross: int, commission: int, net: int, date: string}>
     */
    public function rows(OrderFilter $filter, int $limit, int $offset): array
    {
        $filter = $this->resolve($filter);
        $parts = $this->fulfillments->search($filter, $limit, $offset);
        $totals = $this->ledger->partTotals(array_map(static fn (Fulfillment $p): int => $p->orderId, $parts));
        $rows = [];
        foreach ($parts as $part) {
            $order = wc_get_order($part->orderId) ?: null;
            $vendor = $this->vendors->find($part->vendorId);
            $money = $totals[$part->orderId . ':' . $part->vendorId] ?? ['net' => 0, 'commission' => 0];
            $rows[] = [
                'part' => $part,
                'order' => $order instanceof WC_Order ? $order : null,
                'store' => $vendor?->storeName ?? '#' . $part->vendorId,
                'customer' => $order instanceof WC_Order ? trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()) : '',
                'products' => $order instanceof WC_Order && $vendor !== null ? $this->products($order, (int) $vendor->id, $vendor->userId) : [],
                'billing' => $order instanceof WC_Order ? self::address($order->get_formatted_billing_address()) : '',
                'shipping' => $order instanceof WC_Order ? self::address($order->get_formatted_shipping_address() ?: $order->get_formatted_billing_address()) : '',
                'phone' => $order instanceof WC_Order ? (string) ($order->get_shipping_phone() ?: $order->get_billing_phone()) : '',
                'gross' => $part->subtotal->cents + $part->shipping->cents,
                'commission' => $money['commission'],
                'net' => $money['net'],
                'date' => wp_date('d/m/Y H:i', $part->createdAt->getTimestamp()),
            ];
        }
        return $rows;
    }

    /**
     * Everything an orders screen needs: the page of rows, counts per status, totals and page count.
     *
     * @return array{rows: list<array<string, mixed>>, counts: array<string, int>, sum: array{count: int, gross: int, commission: int, net: int}, page: int, pages: int, filter: OrderFilter}
     */
    public function screen(OrderFilter $filter, int $page, int $perPage): array
    {
        $filter = $this->resolve($filter);
        $counts = $this->fulfillments->countSearch($filter);
        $total = $filter->status === null ? array_sum($counts) : ($counts[$filter->status->value] ?? 0);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        return [
            'rows' => $this->rows($filter, $perPage, ($page - 1) * $perPage),
            'counts' => $counts,
            'sum' => $this->totals($filter),
            'page' => $page,
            'pages' => $pages,
            'filter' => $filter,
        ];
    }

    /** @return array<int, string> store id => name, for the store filter */
    public function storeNames(): array
    {
        $names = [];
        foreach ($this->vendors->list(null, 500, 0) as $vendor) {
            $names[(int) $vendor->id] = $vendor->storeName;
        }
        asort($names, SORT_NATURAL | SORT_FLAG_CASE);
        return $names;
    }

    /**
     * Totals of every order matching the filter (not just the page shown), without loading the orders.
     *
     * @return array{count: int, gross: int, commission: int, net: int}
     */
    public function totals(OrderFilter $filter): array
    {
        $filter = $this->resolve($filter);
        $parts = $this->fulfillments->search($filter, self::EXPORT_LIMIT, 0);
        $money = $this->ledger->partTotals(array_map(static fn (Fulfillment $p): int => $p->orderId, $parts));
        $sum = ['count' => count($parts), 'gross' => 0, 'commission' => 0, 'net' => 0];
        foreach ($parts as $part) {
            $m = $money[$part->orderId . ':' . $part->vendorId] ?? ['net' => 0, 'commission' => 0];
            if ($part->status !== \Cheppi\Sellers\Domain\Order\FulfillmentStatus::Cancelled) {
                $sum['gross'] += $part->subtotal->cents + $part->shipping->cents; // a cancelled sale is not a sale
            }
            $sum['commission'] += $m['commission'];
            $sum['net'] += $m['net'];
        }
        return $sum;
    }

    /** @return array{count: int, gross: int, commission: int, net: int} */
    public static function sum(array $rows): array
    {
        return [
            'count' => count($rows),
            'gross' => array_sum(array_map(static fn (array $r): int => $r['part']->status === \Cheppi\Sellers\Domain\Order\FulfillmentStatus::Cancelled ? 0 : $r['gross'], $rows)),
            'commission' => array_sum(array_column($rows, 'commission')),
            'net' => array_sum(array_column($rows, 'net')),
        ];
    }

    /** @return list<string> "2 × Labial mate (Rojo)" */
    private function products(WC_Order $order, int $vendorId, int $userId): array
    {
        $out = [];
        foreach ($order->get_items('line_item') as $item) {
            if (!$item instanceof WC_Order_Item_Product) {
                continue;
            }
            $tagged = (int) $item->get_meta(OrderSync::META_VENDOR);
            if ($tagged === $vendorId || ($tagged === 0 && (int) get_post_field('post_author', $item->get_product_id()) === $userId)) {
                $out[] = sprintf('%d × %s', $item->get_quantity(), $item->get_name());
            }
        }
        return $out;
    }

    /** WooCommerce's address HTML ("Name<br/>Street<br/>District") as one line. */
    private static function address(string $html): string
    {
        $parts = array_filter(array_map('trim', preg_split('/<br\s*\/?>/i', $html) ?: []));
        return html_entity_decode(wp_strip_all_tags(implode(', ', $parts)), ENT_QUOTES, 'UTF-8');
    }
}
