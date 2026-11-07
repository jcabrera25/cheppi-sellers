<?php
/**
 * Vendor metrics for a period.
 *
 * @var int $days
 * @var array<string, int|float|null> $m
 * @var list<array{date: string, cents: int}> $salesByDay
 * @var list<array{name: string, cents: int, qty: int}> $topProducts
 * @var \Cheppi\Sellers\Domain\Rules\BusinessRules $rules
 */

use Cheppi\Sellers\Infrastructure\WordPress\Front\Charts;
use Cheppi\Sellers\Infrastructure\WordPress\Front\Routes;

defined('ABSPATH') || exit;

$soles = static fn (int $cents): string => 'S/ ' . number_format($cents / 100, 2);
$active = (int) $m['orders'] - (int) $m['cancelled'];
$deliveredPct = $active > 0 ? round((int) $m['delivered'] / $active * 100) : 0;
?>
<div class="ch-toolbar">
    <nav class="ch-tabs" aria-label="<?php esc_attr_e('Periodo', 'cheppi-sellers'); ?>">
        <?php foreach ([7, 30, 90] as $d) : ?>
            <a class="ch-tab" href="<?php echo esc_url(add_query_arg('dias', $d, Routes::metricsUrl())); ?>" <?php echo $days === $d ? 'aria-current="page"' : ''; ?>><?php echo esc_html(sprintf(__('%d días', 'cheppi-sellers'), $d)); ?></a>
        <?php endforeach; ?>
    </nav>
</div>
<section class="ch-grid ch-grid--stats">
    <div class="ch-card ch-stat ch-stat--accent"><span class="ch-stat__label"><?php esc_html_e('Ventas', 'cheppi-sellers'); ?></span><span class="ch-stat__value"><?php echo esc_html($soles((int) $m['sales'])); ?></span><span class="ch-small"><?php echo esc_html(sprintf(__('%d unidades', 'cheppi-sellers'), (int) $m['units'])); ?></span></div>
    <div class="ch-card ch-stat"><span class="ch-stat__label"><?php esc_html_e('Pedidos', 'cheppi-sellers'); ?></span><span class="ch-stat__value"><?php echo (int) $active; ?></span><span class="ch-muted ch-small"><?php echo esc_html(sprintf(__('Ticket promedio %s', 'cheppi-sellers'), $soles((int) $m['avg_ticket']))); ?></span></div>
    <div class="ch-card ch-stat"><span class="ch-stat__label"><?php esc_html_e('Comisión Cheppi', 'cheppi-sellers'); ?></span><span class="ch-stat__value"><?php echo esc_html($soles((int) $m['commission'])); ?></span><span class="ch-muted ch-small"><?php echo esc_html(sprintf(__('Delivery cobrado %s', 'cheppi-sellers'), $soles((int) $m['shipping']))); ?></span></div>
    <div class="ch-card ch-stat"><span class="ch-stat__label"><?php esc_html_e('Cumplimiento', 'cheppi-sellers'); ?></span><span class="ch-stat__value"><?php echo (int) $deliveredPct; ?>%</span><span class="ch-muted ch-small"><?php echo $m['ship_hours'] !== null ? esc_html(sprintf(__('Envías en %s h en promedio', 'cheppi-sellers'), $m['ship_hours'])) : esc_html__('Entregados del periodo', 'cheppi-sellers'); ?></span></div>
</section>
<?php if ((int) $m['late'] > 0) : ?>
    <div class="ch-alert ch-alert--warning" style="margin-top:16px"><p><?php echo esc_html(sprintf(_n('Tienes %1$d pedido sin enviar después de %2$d horas.', 'Tienes %1$d pedidos sin enviar después de %2$d horas.', (int) $m['late'], 'cheppi-sellers'), (int) $m['late'], $rules->dispatchHours)); ?> <a href="<?php echo esc_url(add_query_arg('estado', 'new', Routes::ordersUrl())); ?>"><?php esc_html_e('Ver pedidos', 'cheppi-sellers'); ?></a></p></div>
<?php endif; ?>
<div class="ch-grid ch-grid--2" style="margin-top:16px">
    <section class="ch-card">
        <h2 class="ch-card__title"><?php esc_html_e('Ventas por día', 'cheppi-sellers'); ?></h2>
        <?php echo Charts::salesBars($salesByDay); // phpcs:ignore WordPress.Security.EscapeOutput ?>
    </section>
    <section class="ch-card">
        <h2 class="ch-card__title"><?php esc_html_e('Más vendidos', 'cheppi-sellers'); ?></h2>
        <?php echo $topProducts !== [] ? Charts::ranking($topProducts) : '<p class="ch-muted">' . esc_html__('Sin ventas todavía.', 'cheppi-sellers') . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput ?>
    </section>
</div>
