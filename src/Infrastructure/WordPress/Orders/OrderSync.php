<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Orders;

use Cheppi\Sellers\Application\BusinessRulesRepository;
use Cheppi\Sellers\Application\Fulfillments;
use Cheppi\Sellers\Application\RecordOrderSales;
use Cheppi\Sellers\Application\ReverseOrderSales;
use Cheppi\Sellers\Domain\Commission\CommissionResolver;
use Cheppi\Sellers\Domain\Money\Money;
use Cheppi\Sellers\Domain\Order\FulfillmentStatus;
use Cheppi\Sellers\Domain\Order\SaleLine;
use Cheppi\Sellers\Domain\Promotion\Campaign;
use Cheppi\Sellers\Domain\Order\ShippingCharge;
use Cheppi\Sellers\Domain\Vendor\Vendor;
use Cheppi\Sellers\Infrastructure\WordPress\Front\Routes;
use Cheppi\Sellers\Infrastructure\WordPress\Mail\WpMailNotifier;
use Cheppi\Sellers\Infrastructure\WordPress\Persistence\WpVendorExtras;
use Cheppi\Sellers\Infrastructure\WordPress\Persistence\WpVendorRepository;
use Cheppi\Sellers\Infrastructure\WordPress\Promotions\Campaigns;
use Cheppi\Sellers\Infrastructure\WordPress\Shipping\VendorDeliveryMethod;
use DateTimeImmutable;
use WC_Order;
use WC_Order_Item_Product;
use WC_Order_Item_Shipping;
use WC_Product;

/**
 * Bridges WooCommerce orders and the ledger:
 * - at checkout each line keeps its vendor and the commission in force (frozen),
 * - when the order is paid (processing/completed) sales are recorded and vendors are told,
 * - when it is cancelled, refunded or fails afterwards, everything is reversed.
 */
final class OrderSync
{
    public const META_VENDOR = '_cheppi_vendor_id';
    public const META_BPS = '_cheppi_commission_bps';
    private const PAID = ['processing', 'completed'];
    private const UNDONE = ['cancelled', 'refunded', 'failed'];

    public function __construct(
        private readonly WpVendorRepository $vendors,
        private readonly BusinessRulesRepository $rules,
        private readonly RecordOrderSales $record,
        private readonly ReverseOrderSales $reverse,
        private readonly Fulfillments $fulfillments,
        private readonly WpMailNotifier $mailer,
        private readonly Campaigns $campaigns,
        private readonly WpVendorExtras $extras
    ) {
    }

    public function register(): void
    {
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'tagLine'], 10, 4);
        add_action('woocommerce_checkout_create_order_shipping_item', [$this, 'tagShipping'], 10, 4);
        add_action('woocommerce_order_status_changed', [$this, 'onStatus'], 20, 4);
    }

    /** Freeze vendor and commission on the order line, so later rule changes do not touch past sales. */
    public function tagLine(WC_Order_Item_Product $item, string $cartKey, array $values, WC_Order $order): void
    {
        $product = $values['data'] ?? null;
        if (!$product instanceof WC_Product) {
            return;
        }
        $vendor = $this->vendorOf($product);
        if ($vendor !== null) {
            $item->add_meta_data(self::META_VENDOR, (int) $vendor->id, true);
            $item->add_meta_data(self::META_BPS, $this->commissionBps($vendor, $product), true);
        }
    }

    public function tagShipping(WC_Order_Item_Shipping $item, int|string $packageKey, array $package, WC_Order $order): void
    {
        if (!empty($package['cheppi_vendor_id'])) {
            $item->add_meta_data(self::META_VENDOR, (int) $package['cheppi_vendor_id'], true);
        }
    }

    public function onStatus(int $orderId, string $from, string $to, WC_Order $order): void
    {
        if (in_array($to, self::PAID, true)) {
            $this->recordSales($order);
        } elseif (in_array($to, self::UNDONE, true)) {
            ($this->reverse)($orderId, sprintf('Pedido %s', wc_get_order_status_name($to)), new DateTimeImmutable('now'));
        }
    }

    private function recordSales(WC_Order $order): void
    {
        $lines = [];
        $shipping = [];
        foreach ($order->get_items('line_item') as $item) {
            if (!$item instanceof WC_Order_Item_Product) {
                continue;
            }
            $vendorId = (int) $item->get_meta(self::META_VENDOR);
            $bps = $item->get_meta(self::META_BPS);
            if ($vendorId === 0) {
                // Orders created outside the checkout (admin, API): resolve now.
                $product = $item->get_product();
                $vendor = $product instanceof WC_Product ? $this->vendorOf($product) : null;
                if ($vendor === null) {
                    continue;
                }
                $vendorId = (int) $vendor->id;
                $bps = $this->commissionBps($vendor, $product);
            }
            $lines[] = new SaleLine($item->get_id(), $vendorId, (int) $item->get_quantity(), self::money((float) $item->get_total() + (float) $item->get_total_tax()), (int) $bps);
        }
        foreach ($order->get_items('shipping') as $item) {
            $vendorId = (int) $item->get_meta(self::META_VENDOR);
            if ($item instanceof WC_Order_Item_Shipping && $vendorId > 0 && $item->get_method_id() === VendorDeliveryMethod::ID) {
                $shipping[] = new ShippingCharge($item->get_id(), $vendorId, self::money((float) $item->get_total() + (float) $item->get_total_tax()));
            }
        }

        // Sales count on the day the customer ordered (what "ventas por día" means to a vendor).
        $paid = $order->get_date_created() ?? $order->get_date_paid();
        $paidAt = $paid !== null ? new DateTimeImmutable('@' . $paid->getTimestamp()) : new DateTimeImmutable('now');
        if (!($this->record)($order->get_id(), $lines, $shipping, $paidAt)) {
            return;
        }
        $order->add_order_note(__('Cheppi: ventas registradas para los vendedores.', 'cheppi-sellers'));
        foreach ($this->fulfillments->forOrder($order->get_id()) as $part) {
            $vendor = $this->vendors->find($part->vendorId);
            if ($vendor !== null && $part->status === FulfillmentStatus::New) {
                $this->mailer->newOrder($vendor, $order->get_id(), $part->itemsCount, 'S/ ' . $part->subtotal->add($part->shipping)->toDecimal(), Routes::orderUrl($order->get_id()));
            }
        }
    }

    private function vendorOf(WC_Product $product): ?Vendor
    {
        $postId = $product->get_parent_id() ?: $product->get_id();
        $owner = (int) get_post_field('post_author', $postId);
        $vendor = $owner > 0 ? ($this->vendors->findByUserIds([$owner])[$owner] ?? null) : null;
        return $vendor !== null && $vendor->isPublic() ? $vendor : null;
    }

    private function commissionBps(Vendor $vendor, WC_Product $product): int
    {
        $parent = $product->get_parent_id() ? wc_get_product($product->get_parent_id()) : $product;
        $categories = $parent instanceof WC_Product ? array_map('intval', $parent->get_category_ids()) : [];
        $normal = (new CommissionResolver($this->rules->current()))->resolve($vendor->commissionOverrideBps, $vendor->planId, $categories)->bps;
        return Campaign::commissionFor($normal, $this->campaigns->byIds($this->extras->campaigns((int) $vendor->id)), new DateTimeImmutable('now'));
    }

    private static function money(float $amount): Money
    {
        return Money::ofCents((int) round($amount * 100), get_woocommerce_currency());
    }
}
