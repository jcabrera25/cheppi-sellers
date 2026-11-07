<?php
/**
 * Products list.
 *
 * @var list<WC_Product> $items
 * @var int $total
 * @var int $page
 * @var int $pages
 * @var ?\Cheppi\Sellers\Domain\Catalog\ProductStatus $status
 * @var string $search
 * @var array<string, int> $counts
 * @var ?int $limit
 * @var int $used
 * @var string $layout lista|grid
 */

use Cheppi\Sellers\Domain\Catalog\ProductStatus;
use Cheppi\Sellers\Infrastructure\WordPress\Front\Icons;
use Cheppi\Sellers\Infrastructure\WordPress\Front\Routes;

defined('ABSPATH') || exit;

$chip = static fn (string $s): string => match ($s) {
    'publish' => 'ch-chip ch-chip--success',
    'pending' => 'ch-chip ch-chip--warning',
    default => 'ch-chip ch-chip--neutral',
};
$tabs = ['' => [__('Todos', 'cheppi-sellers'), array_sum($counts)]];
foreach (ProductStatus::cases() as $case) {
    $tabs[$case->value] = [$case->label(), $counts[$case->value] ?? 0];
}
$current = $status?->value ?? '';
$atLimit = $limit !== null && $used >= $limit;
$url = static fn (array $args): string => add_query_arg(array_filter($args, static fn ($v): bool => $v !== '' && $v !== 1), Routes::productsUrl());
?>
<div class="ch-toolbar">
    <nav class="ch-tabs" aria-label="<?php esc_attr_e('Filtrar productos', 'cheppi-sellers'); ?>">
        <?php foreach ($tabs as $value => [$label, $count]) : ?>
            <a class="ch-tab" href="<?php echo esc_url($url(['estado' => $value, 'buscar' => $search])); ?>" <?php echo $current === $value ? 'aria-current="page"' : ''; ?>><?php echo esc_html($label); ?> <span><?php echo (int) $count; ?></span></a>
        <?php endforeach; ?>
    </nav>
    <div class="ch-toolbar__end">
        <div class="ch-viewswitch" role="group" aria-label="<?php esc_attr_e('Vista', 'cheppi-sellers'); ?>">
            <a class="ch-icon-btn" href="<?php echo esc_url($url(['estado' => $current, 'buscar' => $search, 'vista' => 'lista'])); ?>" title="<?php esc_attr_e('Lista', 'cheppi-sellers'); ?>" <?php echo $layout === 'lista' ? 'aria-current="true"' : ''; ?>><?php echo Icons::svg('list', 18); ?><span class="screen-reader-text"><?php esc_html_e('Lista', 'cheppi-sellers'); ?></span></a>
            <a class="ch-icon-btn" href="<?php echo esc_url($url(['estado' => $current, 'buscar' => $search, 'vista' => 'grid'])); ?>" title="<?php esc_attr_e('Cuadrícula', 'cheppi-sellers'); ?>" <?php echo $layout === 'grid' ? 'aria-current="true"' : ''; ?>><?php echo Icons::svg('grid', 18); ?><span class="screen-reader-text"><?php esc_html_e('Cuadrícula', 'cheppi-sellers'); ?></span></a>
        </div>
        <form method="get" action="<?php echo esc_url(Routes::productsUrl()); ?>" role="search">
            <?php if ($current !== '') : ?><input type="hidden" name="estado" value="<?php echo esc_attr($current); ?>"><?php endif; ?>
            <input class="ch-input ch-input--pill" type="search" name="buscar" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Buscar producto', 'cheppi-sellers'); ?>" aria-label="<?php esc_attr_e('Buscar producto', 'cheppi-sellers'); ?>">
        </form>
        <a class="ch-btn ch-btn--ghost" href="<?php echo esc_url(Routes::importUrl()); ?>"><?php esc_html_e('Importar', 'cheppi-sellers'); ?></a>
        <?php if ($atLimit) : ?>
            <span class="ch-btn ch-btn--ghost" aria-disabled="true"><?php esc_html_e('Límite del plan alcanzado', 'cheppi-sellers'); ?></span>
        <?php else : ?>
            <a class="ch-btn ch-btn--primary" href="<?php echo esc_url(Routes::productUrl()); ?>">+ <?php esc_html_e('Nuevo producto', 'cheppi-sellers'); ?></a>
        <?php endif; ?>
    </div>
</div>

<?php if ($limit !== null) : ?>
    <p class="ch-muted ch-meter-label"><?php echo esc_html(sprintf(__('%1$d de %2$d productos de tu plan', 'cheppi-sellers'), $used, $limit)); ?></p>
    <div class="ch-meter" role="progressbar" aria-valuemin="0" aria-valuemax="<?php echo (int) $limit; ?>" aria-valuenow="<?php echo (int) $used; ?>"><span style="width:<?php echo (int) min(100, round($used / max(1, $limit) * 100)); ?>%"></span></div>
<?php endif; ?>

<?php if ($items === []) : ?>
    <section class="ch-card ch-empty-state">
        <span class="ch-status__icon"><?php echo Icons::svg('box', 26); ?></span>
        <h2><?php echo $search !== '' ? esc_html__('No encontramos productos con esa búsqueda', 'cheppi-sellers') : esc_html__('Aún no tienes productos aquí', 'cheppi-sellers'); ?></h2>
        <p class="ch-muted"><?php esc_html_e('Sube tu primer producto con fotos claras y una buena descripción: es lo que más vende.', 'cheppi-sellers'); ?></p>
        <?php if (!$atLimit) : ?><a class="ch-btn ch-btn--primary" href="<?php echo esc_url(Routes::productUrl()); ?>"><?php esc_html_e('Crear producto', 'cheppi-sellers'); ?></a><?php endif; ?>
    </section>
<?php else : ?>
    <ul class="ch-products<?php echo $layout === 'grid' ? ' ch-products--grid' : ''; ?>">
        <?php foreach ($items as $product) :
            $stock = $product->is_type('variable')
                ? sprintf(_n('%d opción', '%d opciones', count($product->get_children()), 'cheppi-sellers'), count($product->get_children()))
                : ($product->get_manage_stock() ? sprintf(__('Stock: %d', 'cheppi-sellers'), (int) $product->get_stock_quantity()) : __('Sin control de stock', 'cheppi-sellers'));
            $statusValue = $product->get_status();
            ?>
            <li class="ch-product">
                <a class="ch-product__link" href="<?php echo esc_url(Routes::productUrl($product->get_id())); ?>">
                    <span class="ch-product__thumb"><?php echo $product->get_image_id()
                        ? wp_get_attachment_image($product->get_image_id(), $layout === 'grid' ? 'woocommerce_thumbnail' : 'thumbnail', false, ['loading' => 'lazy', 'decoding' => 'async', 'alt' => '', 'sizes' => $layout === 'grid' ? '(max-width: 560px) 50vw, 240px' : '56px'])
                        : Icons::svg('box', 22); ?></span>
                    <span class="ch-product__main">
                        <span class="ch-product__name"><?php echo esc_html($product->get_name()); ?></span>
                        <span class="ch-product__meta"><?php echo esc_html($stock); ?></span>
                    </span>
                    <span class="ch-product__price"><?php echo wp_kses_post($product->get_price_html()); ?></span>
                    <span class="<?php echo esc_attr($chip($statusValue)); ?>"><?php echo esc_html(ProductStatus::tryFrom($statusValue)?->label() ?? $statusValue); ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php if ($pages > 1) : ?>
        <nav class="ch-pager" aria-label="<?php esc_attr_e('Páginas', 'cheppi-sellers'); ?>">
            <?php for ($p = 1; $p <= $pages; $p++) : ?>
                <a class="ch-tab" href="<?php echo esc_url($url(['estado' => $current, 'buscar' => $search, 'pagina' => $p])); ?>" <?php echo $p === $page ? 'aria-current="page"' : ''; ?>><?php echo (int) $p; ?></a>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
