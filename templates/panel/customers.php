<?php
/**
 * Customers who bought (management: every store, or one; seller: only the ones who bought from the store).
 *
 * @var list<array<string, mixed>> $customers from WpCustomers
 * @var int $total
 * @var int $page
 * @var int $pages
 * @var int $perPage
 * @var string $search
 * @var string $sort
 * @var int $storeId
 * @var bool $manage
 * @var array<int, string> $stores id => name (management)
 * @var string $baseUrl
 */

use Cheppi\Sellers\Infrastructure\WordPress\Catalog\ProductListQuery;
use Cheppi\Sellers\Infrastructure\WordPress\Front\Icons;
use Cheppi\Sellers\Infrastructure\WordPress\Front\Routes;

defined('ABSPATH') || exit;

$money = static fn (int $c): string => 'S/ ' . number_format($c / 100, 2);
$keep = array_filter(['buscar' => $search, 'orden' => $sort !== 'reciente' ? $sort : '', 'tienda' => $storeId ?: '', 'por_pagina' => $perPage !== 20 ? $perPage : '']);
$detail = static fn (string $key): string => $manage ? Routes::manageCustomerUrl($key) : Routes::customerUrl($key);
$sorts = ['reciente' => __('Compra más reciente', 'cheppi-sellers'), 'gasto' => __('Más dinero gastado', 'cheppi-sellers'), 'pedidos' => __('Más pedidos', 'cheppi-sellers')];
?>
<form method="get" action="<?php echo esc_url($baseUrl); ?>" class="ch-card ch-list-filters" role="search">
    <label class="ch-field ch-list-filters__search"><span class="ch-field__label"><?php esc_html_e('Buscar', 'cheppi-sellers'); ?></span>
        <input class="ch-input" type="search" name="buscar" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Nombre, correo o teléfono', 'cheppi-sellers'); ?>"></label>
    <?php if ($manage) : ?>
        <label class="ch-field"><span class="ch-field__label"><?php esc_html_e('Compró en', 'cheppi-sellers'); ?></span>
            <select class="ch-input" name="tienda"><option value=""><?php esc_html_e('Todas las tiendas', 'cheppi-sellers'); ?></option><?php foreach ($stores as $id => $name) : ?><option value="<?php echo (int) $id; ?>" <?php selected($storeId, $id); ?>><?php echo esc_html($name); ?></option><?php endforeach; ?></select></label>
    <?php endif; ?>
    <label class="ch-field"><span class="ch-field__label"><?php esc_html_e('Ordenar por', 'cheppi-sellers'); ?></span>
        <select class="ch-input" name="orden"><?php foreach ($sorts as $value => $label) : ?><option value="<?php echo esc_attr($value); ?>" <?php selected($sort, $value); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
    <label class="ch-field"><span class="ch-field__label"><?php esc_html_e('Mostrar', 'cheppi-sellers'); ?></span>
        <select class="ch-input" name="por_pagina"><?php foreach (ProductListQuery::PER_PAGE_OPTIONS as $n) : ?><option value="<?php echo (int) $n; ?>" <?php selected($perPage, $n); ?>><?php echo esc_html(sprintf(__('%d por página', 'cheppi-sellers'), $n)); ?></option><?php endforeach; ?></select></label>
    <div class="ch-list-filters__buttons">
        <button class="ch-btn ch-btn--primary ch-btn--sm" type="submit"><?php esc_html_e('Filtrar', 'cheppi-sellers'); ?></button>
        <?php if ($search !== '' || $storeId > 0) : ?><a class="ch-btn ch-btn--ghost ch-btn--sm" href="<?php echo esc_url($baseUrl); ?>"><?php esc_html_e('Limpiar', 'cheppi-sellers'); ?></a><?php endif; ?>
    </div>
</form>

<p class="ch-muted ch-small ch-list-count"><?php echo esc_html(sprintf(_n('%d cliente', '%d clientes', $total, 'cheppi-sellers'), $total)); ?><?php if ($manage && $storeId > 0) : ?> · <?php echo esc_html(sprintf(__('pedidos y gasto en %s', 'cheppi-sellers'), $stores[$storeId] ?? '')); ?><?php elseif (!$manage) : ?> · <?php esc_html_e('pedidos y gasto en tu tienda', 'cheppi-sellers'); ?><?php endif; ?></p>

<?php if ($customers === []) : ?>
    <section class="ch-card ch-empty-state"><span class="ch-status__icon"><?php echo Icons::svg('users', 26); ?></span><p class="ch-muted"><?php echo esc_html($search !== '' ? __('Ningún cliente coincide con la búsqueda.', 'cheppi-sellers') : __('Aún no hay clientes. Aparecerán aquí con su primera compra.', 'cheppi-sellers')); ?></p></section>
<?php else : ?>
    <div class="ch-card ch-otable-wrap">
        <table class="ch-otable ch-ctable">
            <thead><tr>
                <th><?php esc_html_e('Nombre', 'cheppi-sellers'); ?></th>
                <th><?php esc_html_e('Usuario', 'cheppi-sellers'); ?></th>
                <th><?php esc_html_e('Correo', 'cheppi-sellers'); ?></th>
                <?php if ($manage) : ?><th><?php esc_html_e('Tienda', 'cheppi-sellers'); ?></th><?php endif; ?>
                <th><?php esc_html_e('Ubicación', 'cheppi-sellers'); ?></th>
                <th class="ch-num"><?php esc_html_e('Pedidos', 'cheppi-sellers'); ?></th>
                <th class="ch-num"><?php esc_html_e('Dinero gastado', 'cheppi-sellers'); ?></th>
                <th><?php esc_html_e('Último pedido', 'cheppi-sellers'); ?></th>
                <th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($customers as $c) : ?>
                <tr>
                    <td data-label="<?php esc_attr_e('Nombre', 'cheppi-sellers'); ?>"><a class="ch-otable__id" href="<?php echo esc_url($detail($c['key'])); ?>"><?php echo esc_html($c['name']); ?></a></td>
                    <td data-label="<?php esc_attr_e('Usuario', 'cheppi-sellers'); ?>"><?php echo $c['user'] ? esc_html($c['user']->user_login) : '<span class="ch-chip ch-chip--neutral">' . esc_html__('Invitado', 'cheppi-sellers') . '</span>'; ?></td>
                    <td data-label="<?php esc_attr_e('Correo', 'cheppi-sellers'); ?>" class="ch-ctable__mail"><?php echo esc_html($c['email'] ?: '—'); ?></td>
                    <?php if ($manage) : ?><td data-label="<?php esc_attr_e('Tienda', 'cheppi-sellers'); ?>"><?php echo esc_html(implode(', ', array_map(static fn (int $id): string => $stores[$id] ?? '#' . $id, $c['vendors'])) ?: '—'); ?></td><?php endif; ?>
                    <td data-label="<?php esc_attr_e('Ubicación', 'cheppi-sellers'); ?>"><?php echo esc_html($c['location'] ?: '—'); ?></td>
                    <td data-label="<?php esc_attr_e('Pedidos', 'cheppi-sellers'); ?>" class="ch-num"><?php echo (int) $c['orders']; ?></td>
                    <td data-label="<?php esc_attr_e('Gastado', 'cheppi-sellers'); ?>" class="ch-num"><strong><?php echo esc_html($money($c['spent'])); ?></strong></td>
                    <td data-label="<?php esc_attr_e('Último pedido', 'cheppi-sellers'); ?>"><?php echo $c['last_order'] ? esc_html(wp_date('d/m/Y', $c['last_order']->getTimestamp())) : '—'; ?></td>
                    <td><a class="ch-btn ch-btn--ghost ch-btn--sm" href="<?php echo esc_url($detail($c['key'])); ?>"><?php esc_html_e('Ver más', 'cheppi-sellers'); ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pages > 1) : ?>
        <nav class="ch-pager"><?php for ($p = 1; $p <= $pages; $p++) : ?><a class="ch-tab" href="<?php echo esc_url(add_query_arg($keep + ($p > 1 ? ['pagina' => $p] : []), $baseUrl)); ?>" <?php echo $p === $page ? 'aria-current="page"' : ''; ?>><?php echo (int) $p; ?></a><?php endfor; ?></nav>
    <?php endif; ?>
<?php endif; ?>
