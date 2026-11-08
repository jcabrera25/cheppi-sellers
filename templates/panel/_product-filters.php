<?php
/**
 * Filter bar of the product lists: search, category, type, how many per page and (management) store.
 *
 * @var string $action list URL
 * @var \Cheppi\Sellers\Infrastructure\WordPress\Catalog\ProductListQuery $filters
 * @var array<int, string> $categories
 * @var string $search
 * @var string $status current status tab ('' for all)
 * @var array<int, string>|null $stores id => name (management only)
 * @var int $storeId
 */

use Cheppi\Sellers\Infrastructure\WordPress\Catalog\ProductListQuery;

defined('ABSPATH') || exit;

$stores ??= null;
$storeId ??= 0;
$active = $search !== '' || $filters->isFiltered() || $storeId > 0;
?>
<form method="get" action="<?php echo esc_url($action); ?>" class="ch-card ch-list-filters" role="search">
    <?php if ($status !== '') : ?><input type="hidden" name="estado" value="<?php echo esc_attr($status); ?>"><?php endif; ?>
    <label class="ch-field ch-list-filters__search"><span class="ch-field__label"><?php esc_html_e('Buscar', 'cheppi-sellers'); ?></span>
        <input class="ch-input" type="search" name="buscar" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Nombre o SKU', 'cheppi-sellers'); ?>"></label>
    <?php if ($stores !== null) : ?>
        <label class="ch-field"><span class="ch-field__label"><?php esc_html_e('Tienda', 'cheppi-sellers'); ?></span>
            <select class="ch-input" name="tienda"><option value=""><?php esc_html_e('Todas', 'cheppi-sellers'); ?></option><?php foreach ($stores as $id => $name) : ?><option value="<?php echo (int) $id; ?>" <?php selected($storeId, $id); ?>><?php echo esc_html($name); ?></option><?php endforeach; ?></select></label>
    <?php endif; ?>
    <label class="ch-field"><span class="ch-field__label"><?php esc_html_e('Categoría', 'cheppi-sellers'); ?></span>
        <select class="ch-input" name="categoria"><option value=""><?php esc_html_e('Todas', 'cheppi-sellers'); ?></option><?php foreach ($categories as $id => $name) : ?><option value="<?php echo (int) $id; ?>" <?php selected($filters->categoryId, $id); ?>><?php echo esc_html($name); ?></option><?php endforeach; ?></select></label>
    <label class="ch-field"><span class="ch-field__label"><?php esc_html_e('Tipo', 'cheppi-sellers'); ?></span>
        <select class="ch-input" name="tipo"><option value=""><?php esc_html_e('Todos', 'cheppi-sellers'); ?></option><?php foreach (ProductListQuery::typeLabels() as $value => $label) : ?><option value="<?php echo esc_attr($value); ?>" <?php selected($filters->type, $value); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
    <label class="ch-field"><span class="ch-field__label"><?php esc_html_e('Mostrar', 'cheppi-sellers'); ?></span>
        <select class="ch-input" name="por_pagina"><?php foreach (ProductListQuery::PER_PAGE_OPTIONS as $n) : ?><option value="<?php echo (int) $n; ?>" <?php selected($filters->perPage, $n); ?>><?php echo esc_html(sprintf(__('%d por página', 'cheppi-sellers'), $n)); ?></option><?php endforeach; ?></select></label>
    <div class="ch-list-filters__buttons">
        <button class="ch-btn ch-btn--primary ch-btn--sm" type="submit"><?php esc_html_e('Filtrar', 'cheppi-sellers'); ?></button>
        <?php if ($active) : ?><a class="ch-btn ch-btn--ghost ch-btn--sm" href="<?php echo esc_url(add_query_arg(array_filter(['estado' => $status]), $action)); ?>"><?php esc_html_e('Limpiar', 'cheppi-sellers'); ?></a><?php endif; ?>
    </div>
</form>
