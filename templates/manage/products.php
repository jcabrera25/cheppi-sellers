<?php
/**
 * Every product in the marketplace, with moderation and the full editor.
 *
 * @var list<WC_Product> $products
 * @var array<int, \Cheppi\Sellers\Domain\Vendor\Vendor> $owners by user id
 * @var string $status
 * @var ?\Cheppi\Sellers\Domain\Vendor\Vendor $vendor
 * @var string $search
 * @var int $page
 * @var int $pages
 * @var array<string, int> $counts
 * @var ProductListQuery $filters
 * @var array<int, string> $categories
 * @var array<int, string> $stores
 * @var int $total
 */

use Cheppi\Sellers\Infrastructure\WordPress\Admin\ProductModeration;
use Cheppi\Sellers\Infrastructure\WordPress\Catalog\ProductListQuery;
use Cheppi\Sellers\Infrastructure\WordPress\Front\View;
use Cheppi\Sellers\Infrastructure\WordPress\Front\Routes;

defined('ABSPATH') || exit;

$labels = ['' => __('Todos', 'cheppi-sellers'), 'pending' => __('En revisión', 'cheppi-sellers'), 'publish' => __('Publicados', 'cheppi-sellers'), 'draft' => __('Borradores', 'cheppi-sellers')];
$base = Routes::manageUrl('productos');
$keep = $filters->toQuery();
$url = static fn (array $args): string => add_query_arg(array_filter($args + $keep, static fn ($v): bool => $v !== '' && $v !== 0 && $v !== null), $base);
$chip = ['publish' => 'ch-chip--success', 'pending' => 'ch-chip--warning', 'draft' => 'ch-chip--neutral', 'private' => 'ch-chip--neutral'];
$statusLabel = ['publish' => __('Publicado', 'cheppi-sellers'), 'pending' => __('En revisión', 'cheppi-sellers'), 'draft' => __('Borrador', 'cheppi-sellers'), 'private' => __('Privado', 'cheppi-sellers')];
?>
<div class="ch-toolbar">
    <nav class="ch-tabs">
        <?php foreach ($labels as $value => $label) : ?>
            <a class="ch-tab" href="<?php echo esc_url($url(['estado' => $value, 'tienda' => $vendor?->id, 'buscar' => $search])); ?>" <?php echo $status === $value ? 'aria-current="page"' : ''; ?>><?php echo esc_html($label); ?> <span><?php echo (int) ($value === '' ? array_sum($counts) : ($counts[$value] ?? 0)); ?></span></a>
        <?php endforeach; ?>
    </nav>
    <a class="ch-btn ch-btn--primary" href="<?php echo esc_url(Routes::manageProductUrl(0) . ($vendor ? '?tienda=' . (int) $vendor->id : '')); ?>">+ <?php esc_html_e('Nuevo producto', 'cheppi-sellers'); ?></a>
</div>
<?php echo View::capture('panel/_product-filters', ['action' => $base, 'filters' => $filters, 'categories' => $categories, 'search' => $search, 'status' => $status, 'stores' => $stores, 'storeId' => (int) ($vendor?->id ?? 0)]); // phpcs:ignore WordPress.Security.EscapeOutput ?>
<p class="ch-muted ch-small"><?php echo esc_html(sprintf(_n('%d producto', '%d productos', $total, 'cheppi-sellers'), $total)); ?></p>
<?php if ($vendor) : ?>
    <p class="ch-muted"><?php echo esc_html(sprintf(__('Productos de %s', 'cheppi-sellers'), $vendor->storeName)); ?> · <a href="<?php echo esc_url($url(['estado' => $status])); ?>"><?php esc_html_e('Ver todas las tiendas', 'cheppi-sellers'); ?></a></p>
<?php endif; ?>

<div class="ch-card ch-admin__table-card">
    <?php if ($products === []) : ?>
        <div class="ch-empty"><p class="ch-muted"><?php esc_html_e('No hay productos con estos filtros.', 'cheppi-sellers'); ?></p></div>
    <?php else : ?>
        <table class="ch-table-clean ch-manage-products">
            <thead><tr><th></th><th><?php esc_html_e('Producto', 'cheppi-sellers'); ?></th><th><?php esc_html_e('Tienda', 'cheppi-sellers'); ?></th><th><?php esc_html_e('Precio', 'cheppi-sellers'); ?></th><th><?php esc_html_e('Stock', 'cheppi-sellers'); ?></th><th><?php esc_html_e('Estado', 'cheppi-sellers'); ?></th><th><?php esc_html_e('Acciones', 'cheppi-sellers'); ?></th></tr></thead>
            <tbody>
            <?php foreach ($products as $product) :
                $owner = $owners[(int) get_post_field('post_author', $product->get_id())] ?? null;
                $st = $product->get_status();
                ?>
                <tr>
                    <td class="ch-manage-products__thumb"><?php echo $product->get_image_id() ? wp_get_attachment_image($product->get_image_id(), 'thumbnail', false, ['alt' => '', 'loading' => 'lazy', 'sizes' => '48px']) : ''; ?></td>
                    <td data-label="<?php esc_attr_e('Producto', 'cheppi-sellers'); ?>"><a class="ch-strong-link" href="<?php echo esc_url(Routes::manageProductUrl($product->get_id())); ?>"><?php echo esc_html($product->get_name()); ?></a><br><span class="ch-muted ch-small"><?php echo esc_html($product->is_type('variable') ? sprintf(__('Variable · %d variaciones', 'cheppi-sellers'), count($product->get_children())) : __('Simple', 'cheppi-sellers')); ?><?php echo $product->get_sku() ? ' · ' . esc_html($product->get_sku()) : ''; ?></span></td>
                    <td data-label="<?php esc_attr_e('Tienda', 'cheppi-sellers'); ?>"><?php if ($owner) : ?><a href="<?php echo esc_url($url(['tienda' => $owner->id])); ?>"><?php echo esc_html($owner->storeName); ?></a><?php else : ?><span class="ch-muted"><?php esc_html_e('Cheppi', 'cheppi-sellers'); ?></span><?php endif; ?></td>
                    <td data-label="<?php esc_attr_e('Precio', 'cheppi-sellers'); ?>"><?php echo wp_kses_post($product->get_price_html()); ?></td>
                    <td data-label="<?php esc_attr_e('Stock', 'cheppi-sellers'); ?>"><?php echo $product->managing_stock() ? (int) $product->get_stock_quantity() : esc_html($product->is_in_stock() ? __('Disponible', 'cheppi-sellers') : __('Agotado', 'cheppi-sellers')); ?></td>
                    <td data-label="<?php esc_attr_e('Estado', 'cheppi-sellers'); ?>"><span class="ch-chip <?php echo esc_attr($chip[$st] ?? 'ch-chip--neutral'); ?>"><?php echo esc_html($statusLabel[$st] ?? $st); ?></span></td>
                    <td data-label="<?php esc_attr_e('Acciones', 'cheppi-sellers'); ?>" class="ch-manage-products__actions">
                        <?php if ($st === 'pending') : ?>
                            <a class="ch-btn ch-btn--primary ch-btn--sm" href="<?php echo esc_url(ProductModeration::approveUrl($product->get_id())); ?>"><?php esc_html_e('Aprobar', 'cheppi-sellers'); ?></a>
                        <?php endif; ?>
                        <?php if (in_array($st, ['pending', 'publish'], true) && $owner) : ?>
                            <details class="ch-popover">
                                <summary class="ch-btn ch-btn--ghost ch-btn--sm"><?php esc_html_e('Devolver', 'cheppi-sellers'); ?></summary>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ch-popover__body">
                                    <input type="hidden" name="action" value="<?php echo esc_attr(ProductModeration::ACTION_REJECT); ?>">
                                    <input type="hidden" name="product" value="<?php echo (int) $product->get_id(); ?>">
                                    <?php wp_nonce_field(ProductModeration::ACTION_REJECT . $product->get_id()); ?>
                                    <textarea class="ch-input" name="reason" rows="3" required placeholder="<?php esc_attr_e('Qué debe corregir (le llega por correo)', 'cheppi-sellers'); ?>"></textarea>
                                    <button class="ch-btn ch-btn--secondary ch-btn--sm" type="submit"><?php esc_html_e('Pasar a borrador', 'cheppi-sellers'); ?></button>
                                </form>
                            </details>
                        <?php endif; ?>
                        <a class="ch-btn ch-btn--ghost ch-btn--sm" href="<?php echo esc_url(Routes::manageProductUrl($product->get_id())); ?>"><?php esc_html_e('Editar', 'cheppi-sellers'); ?></a>
                        <?php if ($st === 'publish') : ?><a class="ch-btn ch-btn--ghost ch-btn--sm" href="<?php echo esc_url($product->get_permalink()); ?>" target="_blank" rel="noopener"><?php esc_html_e('Ver', 'cheppi-sellers'); ?></a><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php if ($pages > 1) : ?>
    <nav class="ch-pager"><?php for ($p = 1; $p <= $pages; $p++) : ?><a class="ch-tab" href="<?php echo esc_url($url(['estado' => $status, 'tienda' => $vendor?->id, 'buscar' => $search, 'pagina' => $p > 1 ? $p : ''])); ?>" <?php echo $p === $page ? 'aria-current="page"' : ''; ?>><?php echo (int) $p; ?></a><?php endfor; ?></nav>
<?php endif; ?>
