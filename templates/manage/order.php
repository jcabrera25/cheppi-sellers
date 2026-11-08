<?php
/**
 * One customer order with every vendor's part.
 *
 * @var WC_Order $order
 * @var list<array{vendor: \Cheppi\Sellers\Domain\Vendor\Vendor, html: string}> $sections
 */

use Cheppi\Sellers\Infrastructure\WordPress\Admin\VendorsPage;
use Cheppi\Sellers\Infrastructure\WordPress\Front\Routes;

defined('ABSPATH') || exit;
?>
<p><a class="ch-back" href="<?php echo esc_url(Routes::manageUrl('pedidos')); ?>">← <?php esc_html_e('Pedidos', 'cheppi-sellers'); ?></a></p>
<section class="ch-card ch-manage-order">
    <dl class="ch-summary">
        <dt><?php esc_html_e('Estado del pedido', 'cheppi-sellers'); ?></dt><dd><?php echo esc_html(wc_get_order_status_name($order->get_status())); ?></dd>
        <dt><?php esc_html_e('Fecha', 'cheppi-sellers'); ?></dt><dd><?php echo esc_html($order->get_date_created() ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $order->get_date_created()->getTimestamp()) : '—'); ?></dd>
        <dt><?php esc_html_e('Cliente', 'cheppi-sellers'); ?></dt><dd><?php echo esc_html(trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name())); ?><?php echo $order->get_billing_email() ? ' · ' . esc_html($order->get_billing_email()) : ''; ?></dd>
        <dt><?php esc_html_e('Pago', 'cheppi-sellers'); ?></dt><dd><?php echo esc_html($order->get_payment_method_title() ?: '—'); ?></dd>
        <dt><?php esc_html_e('Total', 'cheppi-sellers'); ?></dt><dd><strong><?php echo wp_kses_post($order->get_formatted_order_total()); ?></strong></dd>
    </dl>
    <p class="ch-small"><a href="<?php echo esc_url($order->get_edit_order_url()); ?>"><?php esc_html_e('Abrir en WooCommerce', 'cheppi-sellers'); ?></a></p>
</section>
<?php if ($sections === []) : ?>
    <p class="ch-muted"><?php esc_html_e('Este pedido no tiene productos de tiendas (o aún no está pagado).', 'cheppi-sellers'); ?></p>
<?php endif; ?>
<?php foreach ($sections as $section) : ?>
    <h2 class="ch-manage-order__vendor"><a href="<?php echo esc_url(VendorsPage::detailUrl((int) $section['vendor']->id)); ?>"><?php echo esc_html($section['vendor']->storeName); ?></a></h2>
    <?php echo $section['html']; // phpcs:ignore WordPress.Security.EscapeOutput -- rendered by panel/order ?>
<?php endforeach; ?>
