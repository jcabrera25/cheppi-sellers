<?php
/**
 * Orders table shared by the seller panel and the management panel: actions (print, PDF, Excel),
 * filters (dates, status and — for Cheppi — store), totals and one row per order and store.
 *
 * @var list<array<string, mixed>> $rows from OrdersReport
 * @var bool $manage
 * @var \Cheppi\Sellers\Application\OrderFilter $filter
 * @var array<string, int> $counts by status (same filters, any status)
 * @var array<int, string> $stores id => name (management only)
 * @var int $page
 * @var int $pages
 * @var string $baseUrl
 * @var array{count: int, gross: int, commission: int, net: int} $sum totals of the filtered orders
 */

use Cheppi\Sellers\Domain\Order\FulfillmentStatus;
use Cheppi\Sellers\Infrastructure\WordPress\Front\Icons;
use Cheppi\Sellers\Infrastructure\WordPress\Front\Routes;
use Cheppi\Sellers\Infrastructure\WordPress\Orders\OrdersExport;

defined('ABSPATH') || exit;

$query = $filter->toQuery($manage);
$money = static fn (int $c): string => 'S/ ' . number_format($c / 100, 2);
$chip = static fn (FulfillmentStatus $s): string => match ($s) {
    FulfillmentStatus::New => 'ch-chip--violet',
    FulfillmentStatus::Preparing, FulfillmentStatus::Shipped => 'ch-chip--warning',
    FulfillmentStatus::Delivered => 'ch-chip--success',
    FulfillmentStatus::Cancelled => 'ch-chip--neutral',
};
$detail = static fn (int $orderId): string => $manage ? Routes::manageOrderUrl($orderId) : Routes::orderUrl($orderId);
$withStatus = static fn (?string $status): string => add_query_arg(array_filter(['estado' => $status] + $query, static fn ($v): bool => $v !== null && $v !== ''), $baseUrl);
$currentStore = $manage && $filter->vendorId ? ($stores[$filter->vendorId] ?? '') : '';
?>
<section class="ch-card ch-orders-head">
    <div class="ch-orders-head__top">
        <div>
            <p class="ch-print-only ch-orders-head__title"><?php echo esc_html(implode(' · ', array_filter([
                __('Pedidos', 'cheppi-sellers'),
                $manage ? ($currentStore ?: __('Todas las tiendas', 'cheppi-sellers')) : '',
                $filter->from || $filter->to ? sprintf(__('del %1$s al %2$s', 'cheppi-sellers'), $filter->from?->format('d/m/Y') ?? '…', $filter->to?->format('d/m/Y') ?? '…') : '',
                $filter->status?->label() ?? '',
            ]))); ?></p>
            <p class="ch-muted ch-small"><?php echo esc_html(sprintf(
                _n('%1$d pedido · venta bruta %2$s · comisión %3$s · ganancia %4$s', '%1$d pedidos · venta bruta %2$s · comisión %3$s · ganancia %4$s', $sum['count'], 'cheppi-sellers'),
                $sum['count'], $money($sum['gross']), $money($sum['commission']), $money($sum['net'])
            )); ?></p>
        </div>
        <div class="ch-actions ch-no-print">
            <button type="button" class="ch-btn ch-btn--ghost ch-btn--sm" data-print><?php echo Icons::svg('receipt', 16); ?> <?php esc_html_e('Imprimir', 'cheppi-sellers'); ?></button>
            <a class="ch-btn ch-btn--ghost ch-btn--sm" href="<?php echo esc_url(OrdersExport::url('pdf', $manage, $query)); ?>" target="_blank" rel="noopener"><?php esc_html_e('Exportar PDF', 'cheppi-sellers'); ?></a>
            <a class="ch-btn ch-btn--ghost ch-btn--sm" href="<?php echo esc_url(OrdersExport::url('csv', $manage, $query)); ?>"><?php esc_html_e('CSV', 'cheppi-sellers'); ?></a>
            <a class="ch-btn ch-btn--secondary ch-btn--sm" href="<?php echo esc_url(OrdersExport::url('xlsx', $manage, $query)); ?>"><?php esc_html_e('Exportar Excel', 'cheppi-sellers'); ?></a>
        </div>
    </div>
    <form method="get" action="<?php echo esc_url($baseUrl); ?>" class="ch-orders-filters ch-no-print">
        <label class="ch-field"><span class="ch-field__label"><?php esc_html_e('Desde', 'cheppi-sellers'); ?></span><input class="ch-input" type="date" name="desde" value="<?php echo esc_attr($query['desde'] ?? ''); ?>"></label>
        <label class="ch-field"><span class="ch-field__label"><?php esc_html_e('Hasta', 'cheppi-sellers'); ?></span><input class="ch-input" type="date" name="hasta" value="<?php echo esc_attr($query['hasta'] ?? ''); ?>"></label>
        <?php if ($manage && $stores !== []) : ?>
            <label class="ch-field"><span class="ch-field__label"><?php esc_html_e('Tienda', 'cheppi-sellers'); ?></span>
                <select class="ch-input" name="tienda"><option value=""><?php esc_html_e('Todas', 'cheppi-sellers'); ?></option><?php foreach ($stores as $id => $name) : ?><option value="<?php echo (int) $id; ?>" <?php selected($filter->vendorId, $id); ?>><?php echo esc_html($name); ?></option><?php endforeach; ?></select></label>
        <?php endif; ?>
        <label class="ch-field"><span class="ch-field__label"><?php esc_html_e('Estado', 'cheppi-sellers'); ?></span>
            <select class="ch-input" name="estado"><option value=""><?php esc_html_e('Todos', 'cheppi-sellers'); ?></option><?php foreach (FulfillmentStatus::cases() as $case) : ?><option value="<?php echo esc_attr($case->value); ?>" <?php selected($filter->status?->value, $case->value); ?>><?php echo esc_html($case->label() . ' (' . ($counts[$case->value] ?? 0) . ')'); ?></option><?php endforeach; ?></select></label>
        <div class="ch-orders-filters__buttons">
            <button class="ch-btn ch-btn--primary ch-btn--sm" type="submit"><?php esc_html_e('Filtrar', 'cheppi-sellers'); ?></button>
            <?php if ($query !== []) : ?><a class="ch-btn ch-btn--ghost ch-btn--sm" href="<?php echo esc_url($baseUrl); ?>"><?php esc_html_e('Limpiar', 'cheppi-sellers'); ?></a><?php endif; ?>
        </div>
    </form>
    <nav class="ch-tabs ch-no-print" aria-label="<?php esc_attr_e('Atajos de estado', 'cheppi-sellers'); ?>">
        <a class="ch-tab" href="<?php echo esc_url($withStatus(null)); ?>" <?php echo $filter->status === null ? 'aria-current="page"' : ''; ?>><?php esc_html_e('Todos', 'cheppi-sellers'); ?> <span><?php echo (int) array_sum($counts); ?></span></a>
        <?php foreach (FulfillmentStatus::cases() as $case) : ?>
            <a class="ch-tab" href="<?php echo esc_url($withStatus($case->value)); ?>" <?php echo $filter->status === $case ? 'aria-current="page"' : ''; ?>><?php echo esc_html($case->label()); ?> <span><?php echo (int) ($counts[$case->value] ?? 0); ?></span></a>
        <?php endforeach; ?>
    </nav>
</section>

<?php if ($rows === []) : ?>
    <section class="ch-card ch-empty-state"><span class="ch-status__icon"><?php echo Icons::svg('receipt', 26); ?></span><p class="ch-muted"><?php esc_html_e('No hay pedidos con estos filtros.', 'cheppi-sellers'); ?></p></section>
<?php else : ?>
    <div class="ch-card ch-otable-wrap">
        <table class="ch-otable">
            <thead><tr>
                <th><?php esc_html_e('# Pedido', 'cheppi-sellers'); ?></th>
                <?php if ($manage) : ?><th><?php esc_html_e('Tienda', 'cheppi-sellers'); ?></th><?php endif; ?>
                <th><?php esc_html_e('Productos comprados', 'cheppi-sellers'); ?></th>
                <th><?php esc_html_e('Dirección de facturación', 'cheppi-sellers'); ?></th>
                <th><?php esc_html_e('Dirección de envío', 'cheppi-sellers'); ?></th>
                <th class="ch-num"><?php esc_html_e('Venta bruta', 'cheppi-sellers'); ?></th>
                <th class="ch-num"><?php esc_html_e('Ganancia del vendedor', 'cheppi-sellers'); ?></th>
                <th><?php esc_html_e('Fecha', 'cheppi-sellers'); ?></th>
                <th class="ch-no-print"></th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $r) : ?>
                <tr>
                    <td data-label="<?php esc_attr_e('Pedido', 'cheppi-sellers'); ?>"><a class="ch-otable__id" href="<?php echo esc_url($detail($r['part']->orderId)); ?>">#<?php echo (int) $r['part']->orderId; ?></a><br><span class="ch-chip <?php echo esc_attr($chip($r['part']->status)); ?>"><?php echo esc_html($r['part']->status->label()); ?></span></td>
                    <?php if ($manage) : ?><td data-label="<?php esc_attr_e('Tienda', 'cheppi-sellers'); ?>"><a href="<?php echo esc_url(add_query_arg(['tienda' => $r['part']->vendorId] + array_diff_key($query, ['tienda' => 1]), $baseUrl)); ?>"><?php echo esc_html($r['store']); ?></a></td><?php endif; ?>
                    <td data-label="<?php esc_attr_e('Productos', 'cheppi-sellers'); ?>"><?php echo $r['products'] !== [] ? implode('<br>', array_map('esc_html', $r['products'])) : '—'; ?></td>
                    <td data-label="<?php esc_attr_e('Facturación', 'cheppi-sellers'); ?>" class="ch-otable__addr"><?php echo esc_html($r['billing'] ?: '—'); ?></td>
                    <td data-label="<?php esc_attr_e('Envío', 'cheppi-sellers'); ?>" class="ch-otable__addr"><?php echo esc_html($r['shipping'] ?: '—'); ?><?php echo $r['phone'] !== '' ? '<br><span class="ch-muted">' . esc_html($r['phone']) . '</span>' : ''; ?></td>
                    <td data-label="<?php esc_attr_e('Venta bruta', 'cheppi-sellers'); ?>" class="ch-num"><?php echo esc_html($money($r['gross'])); ?></td>
                    <td data-label="<?php esc_attr_e('Ganancia', 'cheppi-sellers'); ?>" class="ch-num"><strong><?php echo esc_html($money($r['net'])); ?></strong><br><span class="ch-muted ch-small"><?php echo esc_html(sprintf(__('comisión %s', 'cheppi-sellers'), $money($r['commission']))); ?></span></td>
                    <td data-label="<?php esc_attr_e('Fecha', 'cheppi-sellers'); ?>"><?php echo esc_html($r['date']); ?></td>
                    <td class="ch-no-print"><a class="ch-btn ch-btn--ghost ch-btn--sm" href="<?php echo esc_url($detail($r['part']->orderId)); ?>"><?php esc_html_e('Ver más', 'cheppi-sellers'); ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pages > 1) : ?>
        <nav class="ch-pager ch-no-print"><?php for ($p = 1; $p <= $pages; $p++) : ?><a class="ch-tab" href="<?php echo esc_url(add_query_arg($query + ($p > 1 ? ['pagina' => $p] : []), $baseUrl)); ?>" <?php echo $p === $page ? 'aria-current="page"' : ''; ?>><?php echo (int) $p; ?></a><?php endfor; ?></nav>
    <?php endif; ?>
<?php endif; ?>
