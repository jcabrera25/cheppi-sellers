<?php
/**
 * @var int $days
 * @var array<string, int|float|null> $m
 * @var list<array{date: string, cents: int}> $salesByDay
 * @var list<array{vendor_id: int, orders: int, sales: int}> $top
 * @var list<array{order_id: int, vendor_id: int, status: string, created_at: string}> $late
 * @var array<int, string> $names
 * @var int $dispatch
 * @var array<string, int> $counts
 * @var callable(array): string $pageUrl
 */

use Cheppi\Sellers\Domain\Order\FulfillmentStatus;
use Cheppi\Sellers\Infrastructure\WordPress\Admin\VendorsPage;
use Cheppi\Sellers\Infrastructure\WordPress\Front\Charts;
use Cheppi\Sellers\Infrastructure\WordPress\Front\ManageContext;
use Cheppi\Sellers\Infrastructure\WordPress\Front\Routes;

defined('ABSPATH') || exit;

$soles = static fn (int $cents): string => 'S/ ' . number_format($cents / 100, 2);
$active = (int) $m['orders'] - (int) $m['cancelled'];
$maxTop = max(1, ...array_map(static fn (array $r): int => $r['sales'], $top ?: [['sales' => 1]]));
?>
<div class="wrap ch-admin">
    <header class="ch-admin__head ch-admin__head--row">
        <div>
            <h1><?php esc_html_e('Métricas del marketplace', 'cheppi-sellers'); ?></h1>
            <p class="ch-muted"><?php echo esc_html(sprintf(__('%d vendedores aprobados · ventas de productos de vendedores (sin envío).', 'cheppi-sellers'), (int) ($counts['approved'] ?? 0))); ?></p>
        </div>
        <nav class="ch-tabs"><?php foreach ([7, 30, 90] as $d) : ?><a class="ch-tab" href="<?php echo esc_url($pageUrl(['dias' => $d])); ?>" <?php echo $days === $d ? 'aria-current="page"' : ''; ?>><?php echo esc_html(sprintf(__('%d días', 'cheppi-sellers'), $d)); ?></a><?php endforeach; ?></nav>
    </header>
    <div class="ch-grid ch-grid--stats ch-admin__stats">
        <div class="ch-card ch-stat ch-stat--accent"><span class="ch-stat__label"><?php esc_html_e('Ventas (GMV)', 'cheppi-sellers'); ?></span><span class="ch-stat__value"><?php echo esc_html($soles((int) $m['sales'])); ?></span><span class="ch-small"><?php echo esc_html(sprintf(__('%1$d pedidos · ticket %2$s', 'cheppi-sellers'), $active, $soles((int) $m['avg_ticket']))); ?></span></div>
        <div class="ch-card ch-stat"><span class="ch-stat__label"><?php esc_html_e('Comisión Cheppi', 'cheppi-sellers'); ?></span><span class="ch-stat__value"><?php echo esc_html($soles((int) $m['commission'])); ?></span><span class="ch-muted ch-small"><?php echo (int) $m['sales'] > 0 ? esc_html(sprintf(__('%s%% efectivo', 'cheppi-sellers'), number_format((int) $m['commission'] / (int) $m['sales'] * 100, 1))) : ''; ?></span></div>
        <div class="ch-card ch-stat"><span class="ch-stat__label"><?php esc_html_e('Entregados', 'cheppi-sellers'); ?></span><span class="ch-stat__value"><?php echo $active > 0 ? (int) round((int) $m['delivered'] / $active * 100) : 0; ?>%</span><span class="ch-muted ch-small"><?php echo $m['ship_hours'] !== null ? esc_html(sprintf(__('Envío promedio: %s h', 'cheppi-sellers'), $m['ship_hours'])) : ''; ?></span></div>
        <div class="ch-card ch-stat"><span class="ch-stat__label"><?php esc_html_e('Atrasados', 'cheppi-sellers'); ?></span><span class="ch-stat__value"><?php echo (int) $m['late']; ?></span><span class="ch-muted ch-small"><?php echo esc_html(sprintf(__('Sin enviar tras %d h', 'cheppi-sellers'), $dispatch)); ?></span></div>
    </div>
    <div class="ch-grid ch-grid--2">
        <section class="ch-card"><h2 class="ch-card__title"><?php esc_html_e('Ventas por día', 'cheppi-sellers'); ?></h2><?php echo Charts::salesBars($salesByDay); // phpcs:ignore WordPress.Security.EscapeOutput ?></section>
        <section class="ch-card">
            <h2 class="ch-card__title"><?php esc_html_e('Top vendedores', 'cheppi-sellers'); ?></h2>
            <?php if ($top === []) : ?><p class="ch-muted"><?php esc_html_e('Sin ventas en el periodo.', 'cheppi-sellers'); ?></p><?php else : ?>
                <ol class="ch-bars">
                    <?php foreach ($top as $row) : ?>
                        <li><a href="<?php echo esc_url(VendorsPage::detailUrl($row['vendor_id'])); ?>"><?php echo esc_html($names[$row['vendor_id']] ?? ''); ?></a><span class="ch-muted"><?php echo esc_html($soles($row['sales']) . ' · ' . $row['orders']); ?></span><span class="ch-bars__bar"><span style="width:<?php echo (int) max(4, round($row['sales'] / $maxTop * 100)); ?>%"></span></span></li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>
    </div>
    <section class="ch-card ch-admin__table-card" style="margin-top:16px">
        <h2 class="ch-card__title"><?php esc_html_e('Pedidos atrasados', 'cheppi-sellers'); ?></h2>
        <?php if ($late === []) : ?><p class="ch-muted"><?php esc_html_e('Todo al día.', 'cheppi-sellers'); ?></p><?php else : ?>
            <table class="ch-table-clean"><thead><tr><th><?php esc_html_e('Pedido', 'cheppi-sellers'); ?></th><th><?php esc_html_e('Vendedor', 'cheppi-sellers'); ?></th><th><?php esc_html_e('Estado', 'cheppi-sellers'); ?></th><th><?php esc_html_e('Desde', 'cheppi-sellers'); ?></th></tr></thead><tbody>
            <?php foreach ($late as $row) : ?>
                <tr><td><a href="<?php echo esc_url(ManageContext::useFront() ? Routes::manageOrderUrl($row['order_id']) : admin_url('admin.php?page=wc-orders&action=edit&id=' . $row['order_id'])); ?>">#<?php echo (int) $row['order_id']; ?></a></td><td><?php echo esc_html($names[$row['vendor_id']] ?? ''); ?></td><td><?php echo esc_html(FulfillmentStatus::tryFrom($row['status'])?->label() ?? $row['status']); ?></td><td><?php echo esc_html(wp_date('d/m H:i', strtotime($row['created_at'] . ' UTC'))); ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
        <?php endif; ?>
    </section>
</div>
