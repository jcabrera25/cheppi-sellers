<?php
/**
 * One order, limited to this vendor's products.
 *
 * @var \Cheppi\Sellers\Domain\Vendor\Vendor $vendor
 * @var \Cheppi\Sellers\Domain\Order\Fulfillment $part
 * @var WC_Order $order
 * @var array<int, WC_Order_Item_Product> $items
 * @var list<\Cheppi\Sellers\Domain\Ledger\LedgerEntry> $entries
 * @var int $adminVendorId set when shown in the management panel (one block per vendor)
 */

use Cheppi\Sellers\Domain\Ledger\LedgerEntryType;
use Cheppi\Sellers\Domain\Money\Money;
use Cheppi\Sellers\Domain\Order\FulfillmentStatus;
use Cheppi\Sellers\Domain\Rules\Percent;
use Cheppi\Sellers\Infrastructure\WordPress\Front\OrdersHandler;
use Cheppi\Sellers\Infrastructure\WordPress\Front\Routes;

defined('ABSPATH') || exit;

$sum = static function (array $entries, LedgerEntryType $type): Money {
    $cents = 0;
    foreach ($entries as $e) {
        $cents += $e->type === $type ? $e->amount->cents : 0;
    }
    return Money::ofCents($cents);
};
$sales = $sum($entries, LedgerEntryType::Sale);
$commission = $sum($entries, LedgerEntryType::Commission);
$shipping = $sum($entries, LedgerEntryType::Shipping);
$refunds = $sum($entries, LedgerEntryType::Refund);
$net = $sales->add($commission)->add($shipping)->add($refunds);
$bps = $entries[0]->commissionBps ?? null;
$soles = static fn (Money $m): string => ($m->cents < 0 ? '− ' : '') . 'S/ ' . number_format(abs($m->cents) / 100, 2);

$phone = $order->get_shipping_phone() ?: $order->get_billing_phone();
$address = array_filter([
    $order->get_shipping_address_1() ?: $order->get_billing_address_1(),
    $order->get_shipping_address_2() ?: $order->get_billing_address_2(),
    $order->get_shipping_city() ?: $order->get_billing_city(),
]);
$name = trim(($order->get_shipping_first_name() ?: $order->get_billing_first_name()) . ' ' . ($order->get_shipping_last_name() ?: $order->get_billing_last_name()));
$next = $part->status->next();
$whatsapp = preg_replace('/\D+/', '', (string) $phone);
?>
<?php $adminVendorId = (int) ($adminVendorId ?? 0); ?>
<?php if ($adminVendorId === 0) : ?><p><a class="ch-back" href="<?php echo esc_url(Routes::ordersUrl()); ?>">← <?php esc_html_e('Pedidos', 'cheppi-sellers'); ?></a></p><?php endif; ?>
<div class="ch-grid ch-grid--2">
    <div class="ch-grid">
        <section class="ch-card">
            <div class="ch-card__head">
                <h2 class="ch-card__title"><?php esc_html_e('Productos', 'cheppi-sellers'); ?></h2>
                <span class="ch-muted ch-small"><?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $part->createdAt->getTimestamp())); ?></span>
            </div>
            <ul class="ch-lines">
                <?php foreach ($items as $item) :
                    $product = $item->get_product();
                    ?>
                    <li class="ch-line">
                        <span class="ch-product__thumb"><?php echo $product && $product->get_image_id() ? wp_get_attachment_image($product->get_image_id(), 'thumbnail', false, ['alt' => '', 'sizes' => '56px', 'loading' => 'lazy']) : ''; ?></span>
                        <span class="ch-product__main">
                            <span class="ch-product__name"><?php echo esc_html($item->get_name()); ?></span>
                            <span class="ch-product__meta"><?php echo esc_html(sprintf(__('Cantidad: %d', 'cheppi-sellers'), $item->get_quantity())); ?><?php echo $product && $product->get_sku() ? ' · SKU ' . esc_html($product->get_sku()) : ''; ?></span>
                        </span>
                        <span class="ch-product__price">S/ <?php echo esc_html(number_format((float) $item->get_total() + (float) $item->get_total_tax(), 2)); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="ch-card">
            <h2 class="ch-card__title"><?php esc_html_e('Entrega', 'cheppi-sellers'); ?></h2>
            <dl class="ch-summary">
                <dt><?php esc_html_e('Cliente', 'cheppi-sellers'); ?></dt><dd><?php echo esc_html($name !== '' ? $name : '—'); ?></dd>
                <dt><?php esc_html_e('Celular', 'cheppi-sellers'); ?></dt><dd><?php if ($phone) : ?><a href="tel:<?php echo esc_attr($whatsapp); ?>"><?php echo esc_html($phone); ?></a> · <a href="<?php echo esc_url('https://wa.me/' . (strlen($whatsapp) === 9 ? '51' . $whatsapp : $whatsapp)); ?>" target="_blank" rel="noopener">WhatsApp</a><?php else : ?>—<?php endif; ?></dd>
                <dt><?php esc_html_e('Dirección', 'cheppi-sellers'); ?></dt><dd><?php echo esc_html($address !== [] ? implode(', ', $address) : '—'); ?></dd>
                <?php if ($order->get_customer_note() !== '') : ?><dt><?php esc_html_e('Nota', 'cheppi-sellers'); ?></dt><dd><?php echo esc_html($order->get_customer_note()); ?></dd><?php endif; ?>
            </dl>
        </section>
    </div>

    <div class="ch-grid ch-sticky">
        <section class="ch-card ch-stack">
            <div class="ch-card__head">
                <h2 class="ch-card__title"><?php esc_html_e('Estado', 'cheppi-sellers'); ?></h2>
                <span class="ch-chip <?php echo $part->status === FulfillmentStatus::Delivered ? 'ch-chip--success' : ($part->status === FulfillmentStatus::Cancelled ? 'ch-chip--neutral' : 'ch-chip--warning'); ?>"><?php echo esc_html($part->status->label()); ?></span>
            </div>
            <ol class="ch-steps-mini">
                <?php foreach ([FulfillmentStatus::New, FulfillmentStatus::Preparing, FulfillmentStatus::Shipped, FulfillmentStatus::Delivered] as $i => $step) :
                    $order_index = array_search($part->status, [FulfillmentStatus::New, FulfillmentStatus::Preparing, FulfillmentStatus::Shipped, FulfillmentStatus::Delivered], true);
                    ?>
                    <li data-done="<?php echo $order_index !== false && $i <= $order_index ? 'true' : 'false'; ?>"><?php echo esc_html($step->label()); ?></li>
                <?php endforeach; ?>
            </ol>
            <?php if ($part->status->isOpen()) : ?>
                <form class="ch-stack" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="<?php echo esc_attr(OrdersHandler::UPDATE_ORDER); ?>">
                    <input type="hidden" name="order_id" value="<?php echo (int) $part->orderId; ?>">
                    <?php wp_nonce_field(OrdersHandler::UPDATE_ORDER . $part->orderId); ?>
                    <?php if ($adminVendorId > 0) : ?><input type="hidden" name="vendor_id" value="<?php echo (int) $adminVendorId; ?>"><?php endif; ?>
                    <label class="ch-field">
                        <span class="ch-field__label"><?php esc_html_e('Seguimiento o datos del motorizado', 'cheppi-sellers'); ?> <small><?php esc_html_e('lo verá el cliente', 'cheppi-sellers'); ?></small></span>
                        <input class="ch-input" name="tracking" maxlength="200" value="<?php echo esc_attr($part->tracking); ?>" placeholder="<?php esc_attr_e('Ej.: Motorizado Luis, 987 654 321, llega 3-5 p. m.', 'cheppi-sellers'); ?>">
                    </label>
                    <div class="ch-actions">
                        <?php foreach ($next as $i => $state) : ?>
                            <button type="submit" name="status" value="<?php echo esc_attr($state->value); ?>" class="ch-btn <?php echo $i === 0 ? 'ch-btn--primary' : 'ch-btn--ghost'; ?>"><?php echo esc_html(sprintf(__('Marcar «%s»', 'cheppi-sellers'), $state->label())); ?></button>
                        <?php endforeach; ?>
                        <button type="submit" name="status" value="<?php echo esc_attr($part->status->value); ?>" class="ch-btn ch-btn--ghost"><?php esc_html_e('Guardar seguimiento', 'cheppi-sellers'); ?></button>
                    </div>
                </form>
            <?php elseif ($part->tracking !== '') : ?>
                <p class="ch-muted"><?php echo esc_html($part->tracking); ?></p>
            <?php endif; ?>
        </section>

        <section class="ch-card">
            <h2 class="ch-card__title"><?php echo $adminVendorId > 0 ? esc_html__('Liquidación del vendedor', 'cheppi-sellers') : esc_html__('Tu dinero', 'cheppi-sellers'); ?></h2>
            <dl class="ch-summary ch-summary--money">
                <dt><?php esc_html_e('Venta', 'cheppi-sellers'); ?></dt><dd><?php echo esc_html($soles($sales)); ?></dd>
                <dt><?php echo esc_html($bps !== null ? sprintf(__('Comisión Cheppi (%s%%)', 'cheppi-sellers'), Percent::fromBps($bps)) : __('Comisión Cheppi', 'cheppi-sellers')); ?></dt><dd><?php echo esc_html($soles($commission)); ?></dd>
                <?php if ($shipping->cents !== 0) : ?><dt><?php esc_html_e('Delivery cobrado', 'cheppi-sellers'); ?></dt><dd><?php echo esc_html($soles($shipping)); ?></dd><?php endif; ?>
                <?php if ($refunds->cents !== 0) : ?><dt><?php esc_html_e('Anulado', 'cheppi-sellers'); ?></dt><dd><?php echo esc_html($soles($refunds)); ?></dd><?php endif; ?>
                <dt><strong><?php esc_html_e('Recibes', 'cheppi-sellers'); ?></strong></dt><dd><strong><?php echo esc_html($soles($net)); ?></strong></dd>
            </dl>
            <p class="ch-muted ch-small"><?php echo esc_html($part->status === FulfillmentStatus::Delivered
                ? __('Se libera al terminar el plazo de devolución y se paga en el siguiente corte.', 'cheppi-sellers')
                : __('Se libera cuando marques el pedido como entregado y termine el plazo de devolución.', 'cheppi-sellers')); ?></p>
        </section>
    </div>
</div>
