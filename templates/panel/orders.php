<?php
/**
 * Vendor orders (their part of each customer order).
 *
 * @var list<\Cheppi\Sellers\Domain\Order\Fulfillment> $parts
 * @var array<int, WC_Order> $orders
 * @var ?\Cheppi\Sellers\Domain\Order\FulfillmentStatus $status
 * @var array<string, int> $counts
 * @var int $page
 * @var int $pages
 */

use Cheppi\Sellers\Domain\Order\FulfillmentStatus;
use Cheppi\Sellers\Infrastructure\WordPress\Front\Icons;
use Cheppi\Sellers\Infrastructure\WordPress\Front\Routes;

defined('ABSPATH') || exit;

$chip = static fn (FulfillmentStatus $s): string => match ($s) {
    FulfillmentStatus::New => 'ch-chip ch-chip--violet',
    FulfillmentStatus::Preparing, FulfillmentStatus::Shipped => 'ch-chip ch-chip--warning',
    FulfillmentStatus::Delivered => 'ch-chip ch-chip--success',
    FulfillmentStatus::Cancelled => 'ch-chip ch-chip--neutral',
};
$current = $status?->value ?? '';
$tabs = ['' => [__('Todos', 'cheppi-sellers'), array_sum($counts)]];
foreach (FulfillmentStatus::cases() as $case) {
    $tabs[$case->value] = [$case->label(), $counts[$case->value] ?? 0];
}
$url = static fn (array $args): string => add_query_arg(array_filter($args, static fn ($v): bool => $v !== '' && $v !== 1), Routes::ordersUrl());
?>
<div class="ch-toolbar">
    <nav class="ch-tabs" aria-label="<?php esc_attr_e('Filtrar pedidos', 'cheppi-sellers'); ?>">
        <?php foreach ($tabs as $value => [$label, $count]) : ?>
            <a class="ch-tab" href="<?php echo esc_url($url(['estado' => $value])); ?>" <?php echo $current === $value ? 'aria-current="page"' : ''; ?>><?php echo esc_html($label); ?> <span><?php echo (int) $count; ?></span></a>
        <?php endforeach; ?>
    </nav>
</div>

<?php if ($parts === []) : ?>
    <section class="ch-card ch-empty-state">
        <span class="ch-status__icon"><?php echo Icons::svg('receipt', 26); ?></span>
        <h2><?php esc_html_e('Aún no hay pedidos aquí', 'cheppi-sellers'); ?></h2>
        <p class="ch-muted"><?php esc_html_e('Cuando alguien compre tus productos te avisaremos por correo y lo verás en esta lista.', 'cheppi-sellers'); ?></p>
    </section>
<?php else : ?>
    <ul class="ch-orders">
        <?php foreach ($parts as $part) :
            $order = $orders[$part->orderId] ?? null;
            $district = $order ? ($order->get_shipping_city() ?: $order->get_billing_city()) : '';
            ?>
            <li>
                <a class="ch-order" href="<?php echo esc_url(Routes::orderUrl($part->orderId)); ?>">
                    <span class="ch-order__id">#<?php echo (int) $part->orderId; ?></span>
                    <span class="ch-order__main">
                        <span class="ch-order__who"><?php echo esc_html($order ? trim($order->get_shipping_first_name() ?: $order->get_billing_first_name()) : '—'); ?><?php echo $district !== '' ? ' · ' . esc_html($district) : ''; ?></span>
                        <span class="ch-order__meta"><?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $part->createdAt->getTimestamp())); ?> · <?php echo esc_html(sprintf(_n('%d producto', '%d productos', $part->itemsCount, 'cheppi-sellers'), $part->itemsCount)); ?></span>
                    </span>
                    <span class="ch-order__total">S/ <?php echo esc_html(number_format($part->subtotal->add($part->shipping)->cents / 100, 2)); ?></span>
                    <span class="<?php echo esc_attr($chip($part->status)); ?>"><?php echo esc_html($part->status->label()); ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php if ($pages > 1) : ?>
        <nav class="ch-pager" aria-label="<?php esc_attr_e('Páginas', 'cheppi-sellers'); ?>">
            <?php for ($p = 1; $p <= $pages; $p++) : ?>
                <a class="ch-tab" href="<?php echo esc_url($url(['estado' => $current, 'pagina' => $p])); ?>" <?php echo $p === $page ? 'aria-current="page"' : ''; ?>><?php echo (int) $p; ?></a>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
