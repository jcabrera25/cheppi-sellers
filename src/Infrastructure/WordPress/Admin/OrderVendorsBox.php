<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Admin;

use Cheppi\Sellers\Application\Fulfillments;
use Cheppi\Sellers\Application\Ledger;
use Cheppi\Sellers\Domain\Ledger\LedgerEntryType;
use Cheppi\Sellers\Infrastructure\WordPress\Persistence\WpVendorRepository;

/**
 * Side box on the WooCommerce order screen: each vendor's part, its status and Cheppi's commission.
 */
final class OrderVendorsBox
{
    public function __construct(
        private readonly Fulfillments $fulfillments,
        private readonly Ledger $ledger,
        private readonly WpVendorRepository $vendors
    ) {
    }

    public function register(): void
    {
        add_action('add_meta_boxes', function (): void {
            $screen = function_exists('wc_get_page_screen_id') ? wc_get_page_screen_id('shop-order') : 'shop_order';
            add_meta_box('cheppi-order-vendors', __('Vendedores (Cheppi)', 'cheppi-sellers'), [$this, 'render'], $screen, 'side', 'high');
        });
    }

    /** @param \WP_Post|\WC_Order $postOrOrder */
    public function render($postOrOrder): void
    {
        $orderId = $postOrOrder instanceof \WC_Order ? $postOrOrder->get_id() : (int) $postOrOrder->ID;
        $parts = $this->fulfillments->forOrder($orderId);
        if ($parts === []) {
            echo '<p>' . esc_html__('Sin productos de vendedores, o el pedido aún no está pagado.', 'cheppi-sellers') . '</p>';
            return;
        }
        $commission = [];
        foreach ($this->ledger->forOrder($orderId) as $entry) {
            if ($entry->type === LedgerEntryType::Commission) {
                $commission[$entry->vendorId] = ($commission[$entry->vendorId] ?? 0) - $entry->amount->cents;
            }
        }
        echo '<ul style="margin:0">';
        foreach ($parts as $part) {
            $vendor = $this->vendors->find($part->vendorId);
            printf(
                '<li style="padding:8px 0;border-bottom:1px solid #eee"><a href="%s"><strong>%s</strong></a><br>%s · S/ %s<br><span style="color:#666">%s S/ %s%s</span></li>',
                esc_url(VendorsPage::detailUrl($part->vendorId)),
                esc_html($vendor?->storeName ?? '#' . $part->vendorId),
                esc_html($part->status->label()),
                esc_html($part->subtotal->add($part->shipping)->toDecimal()),
                esc_html__('Comisión Cheppi:', 'cheppi-sellers'),
                esc_html(number_format(($commission[$part->vendorId] ?? 0) / 100, 2)),
                $part->tracking !== '' ? '<br>' . esc_html($part->tracking) : ''
            );
        }
        echo '</ul>';
    }
}
