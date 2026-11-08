<?php
/**
 * Every vendor's part of every order.
 *
 * @var list<\Cheppi\Sellers\Domain\Order\Fulfillment> $parts
 * @var array<string, int> $counts
 * @var array<int, ?\Cheppi\Sellers\Domain\Vendor\Vendor> $vendors
 * @var array<int, WC_Order> $orders
 * @var ?\Cheppi\Sellers\Domain\Order\FulfillmentStatus $status
 * @var int $page
 * @var int $pages
 */

use Cheppi\Sellers\Domain\Order\FulfillmentStatus;
use Cheppi\Sellers\Infrastructure\WordPress\Front\Routes;

defined('ABSPATH') || exit;

$base = Routes::manageUrl('pedidos');
$chip = static fn (FulfillmentStatus $s): string => match ($s) {
    FulfillmentStatus::New => 'ch-chip--violet',
    FulfillmentStatus::Preparing, FulfillmentStatus::Shipped => 'ch-chip--warning',
    FulfillmentStatus::Delivered => 'ch-chip--success',
    FulfillmentStatus::Cancelled => 'ch-chip--neutral',
};
?>
<div class="ch-toolbar">
    <nav class="ch-tabs">
        <a class="ch-tab" href="<?php echo esc_url($base); ?>" <?php echo $status === null ? 'aria-current="page"' : ''; ?>><?php esc_html_e('Todos', 'cheppi-sellers'); ?> <span><?php echo (int) array_sum($counts); ?></span></a>
        <?php foreach (FulfillmentStatus::cases() as $case) : ?>
            <a class="ch-tab" href="<?php echo esc_url(add_query_arg('estado', $case->value, $base)); ?>" <?php echo $status === $case ? 'aria-current="page"' : ''; ?>><?php echo esc_html($case->label()); ?> <span><?php echo (int) ($counts[$case->value] ?? 0); ?></span></a>
        <?php endforeach; ?>
    </nav>
</div>
<?php if ($parts === []) : ?>
    <section class="ch-card ch-empty-state"><p class="ch-muted"><?php esc_html_e('No hay pedidos en este estado.', 'cheppi-sellers'); ?></p></section>
<?php else : ?>
    <ul class="ch-orders">
        <?php foreach ($parts as $part) :
            $order = $orders[$part->orderId] ?? null;
            $vendor = $vendors[$part->vendorId] ?? null;
            ?>
            <li>
                <a class="ch-order ch-order--manage" href="<?php echo esc_url(Routes::manageOrderUrl($part->orderId)); ?>">
                    <span class="ch-order__id">#<?php echo (int) $part->orderId; ?></span>
                    <span class="ch-order__main">
                        <span class="ch-order__who"><?php echo esc_html($vendor?->storeName ?? '—'); ?> → <?php echo esc_html($order ? trim($order->get_shipping_first_name() ?: $order->get_billing_first_name()) . ' · ' . ($order->get_shipping_city() ?: $order->get_billing_city()) : '—'); ?></span>
                        <span class="ch-order__meta"><?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $part->createdAt->getTimestamp())); ?> · <?php echo esc_html(sprintf(_n('%d producto', '%d productos', $part->itemsCount, 'cheppi-sellers'), $part->itemsCount)); ?><?php echo $part->tracking !== '' ? ' · ' . esc_html($part->tracking) : ''; ?></span>
                    </span>
                    <span class="ch-order__total">S/ <?php echo esc_html(number_format($part->subtotal->add($part->shipping)->cents / 100, 2)); ?></span>
                    <span class="ch-chip <?php echo esc_attr($chip($part->status)); ?>"><?php echo esc_html($part->status->label()); ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php if ($pages > 1) : ?>
        <nav class="ch-pager"><?php for ($p = 1; $p <= $pages; $p++) : ?><a class="ch-tab" href="<?php echo esc_url(add_query_arg(array_filter(['estado' => $status?->value, 'pagina' => $p > 1 ? $p : null]), $base)); ?>" <?php echo $p === $page ? 'aria-current="page"' : ''; ?>><?php echo (int) $p; ?></a><?php endfor; ?></nav>
    <?php endif; ?>
<?php endif; ?>
