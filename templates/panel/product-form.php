<?php
/**
 * Create / edit a product: simple (one price) or with options (shade, size…).
 * Works without JavaScript; the panel module adds option rows, photo previews and the type switch.
 *
 * @var int $productId
 * @var array<string, mixed> $values
 * @var list<array{id: int, url: string}> $images
 * @var array<string, string> $errors
 * @var array<int, string> $categories
 * @var bool $moderated
 */

use Cheppi\Sellers\Domain\Catalog\ProductStatus;
use Cheppi\Sellers\Infrastructure\WordPress\Front\Icons;
use Cheppi\Sellers\Infrastructure\WordPress\Front\PanelHandler;
use Cheppi\Sellers\Infrastructure\WordPress\Front\Routes;

defined('ABSPATH') || exit;

$val = static fn (string $k): string => isset($values[$k]) && is_scalar($values[$k]) ? (string) $values[$k] : '';
$err = static function (string $k) use ($errors): void {
    printf('<span class="ch-field__error" data-error-for="%s">%s</span>', esc_attr($k), esc_html($errors[$k] ?? ''));
};
$inv = static fn (string $k): string => isset($errors[$k]) ? ' aria-invalid="true"' : '';
$isVariable = $val('type') === 'variable';
$variations = is_array($values['variations'] ?? null) && $values['variations'] !== [] ? array_values($values['variations']) : [['option' => '', 'price' => '', 'sale_price' => '', 'sku' => '', 'stock' => '']];
$status = ProductStatus::tryFrom($val('status'));
$publishLabel = $status === ProductStatus::Published
    ? __('Guardar cambios', 'cheppi-sellers')
    : ($moderated ? __('Enviar a revisión', 'cheppi-sellers') : __('Publicar', 'cheppi-sellers'));
$variationRow = static function (int|string $i, array $row) use ($errors): string {
    $v = static fn (string $k): string => esc_attr((string) ($row[$k] ?? ''));
    $e = static fn (string $k): string => isset($errors["variations.$i.$k"]) ? ' aria-invalid="true" title="' . esc_attr($errors["variations.$i.$k"]) . '"' : '';
    return '<tr>'
        . '<td><input type="hidden" name="variations[' . $i . '][id]" value="' . $v('id') . '"><input class="ch-input" name="variations[' . $i . '][option]" value="' . $v('option') . '" placeholder="' . esc_attr__('Ej. Nude 01', 'cheppi-sellers') . '" aria-label="' . esc_attr__('Opción', 'cheppi-sellers') . '"' . $e('option') . '></td>'
        . '<td><input class="ch-input" name="variations[' . $i . '][price]" inputmode="decimal" value="' . $v('price') . '" aria-label="' . esc_attr__('Precio', 'cheppi-sellers') . '"' . $e('price') . '></td>'
        . '<td><input class="ch-input" name="variations[' . $i . '][sale_price]" inputmode="decimal" value="' . $v('sale_price') . '" aria-label="' . esc_attr__('Oferta', 'cheppi-sellers') . '"' . $e('sale_price') . '></td>'
        . '<td><input class="ch-input" name="variations[' . $i . '][stock]" inputmode="numeric" value="' . $v('stock') . '" aria-label="' . esc_attr__('Stock', 'cheppi-sellers') . '"' . $e('stock') . '></td>'
        . '<td><input class="ch-input" name="variations[' . $i . '][sku]" value="' . $v('sku') . '" aria-label="' . esc_attr__('Código', 'cheppi-sellers') . '"' . $e('sku') . '></td>'
        . '<td><button type="button" class="ch-icon-btn" data-remove-row aria-label="' . esc_attr__('Quitar opción', 'cheppi-sellers') . '">&times;</button></td>'
        . '</tr>';
};
?>
<p class="ch-back"><a href="<?php echo esc_url(Routes::productsUrl()); ?>">&larr; <?php esc_html_e('Productos', 'cheppi-sellers'); ?></a>
    <?php if ($status !== null) : ?> · <span class="ch-chip <?php echo $status === ProductStatus::Published ? 'ch-chip--success' : ($status === ProductStatus::Pending ? 'ch-chip--warning' : 'ch-chip--neutral'); ?>"><?php echo esc_html($status->label()); ?></span><?php endif; ?>
    <?php if ($status === ProductStatus::Published && !empty($values['permalink'])) : ?> · <a href="<?php echo esc_url((string) $values['permalink']); ?>" target="_blank" rel="noopener"><?php esc_html_e('Ver en la tienda', 'cheppi-sellers'); ?></a><?php endif; ?>
</p>

<form class="ch-grid ch-grid--2" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-product-form>
    <input type="hidden" name="action" value="<?php echo esc_attr(PanelHandler::SAVE_PRODUCT); ?>">
    <input type="hidden" name="product_id" value="<?php echo (int) $productId; ?>">
    <?php wp_nonce_field(PanelHandler::SAVE_PRODUCT . $productId); ?>

    <div class="ch-grid">
        <section class="ch-card ch-stack">
            <h2 class="ch-card__title"><?php esc_html_e('Información', 'cheppi-sellers'); ?></h2>
            <label class="ch-field">
                <span class="ch-field__label"><?php esc_html_e('Nombre del producto', 'cheppi-sellers'); ?></span>
                <input class="ch-input" name="name" required minlength="3" maxlength="120" value="<?php echo esc_attr($val('name')); ?>"<?php echo $inv('name'); ?>>
                <?php $err('name'); ?>
            </label>
            <label class="ch-field">
                <span class="ch-field__label"><?php esc_html_e('Descripción', 'cheppi-sellers'); ?></span>
                <textarea class="ch-input" name="description" rows="6" maxlength="5000" placeholder="<?php esc_attr_e('Qué es, para quién, cómo se usa, contenido neto…', 'cheppi-sellers'); ?>"<?php echo $inv('description'); ?>><?php echo esc_textarea($val('description')); ?></textarea>
                <?php $err('description'); ?>
            </label>
            <label class="ch-field">
                <span class="ch-field__label"><?php esc_html_e('Categoría', 'cheppi-sellers'); ?></span>
                <select class="ch-input" name="category" required<?php echo $inv('category'); ?>>
                    <option value=""><?php esc_html_e('Elige una', 'cheppi-sellers'); ?></option>
                    <?php foreach ($categories as $id => $name) : ?>
                        <option value="<?php echo (int) $id; ?>" <?php selected($val('category'), (string) $id); ?>><?php echo esc_html($name); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php $err('category'); ?>
            </label>
        </section>

        <section class="ch-card ch-stack">
            <h2 class="ch-card__title"><?php esc_html_e('Precio y stock', 'cheppi-sellers'); ?></h2>
            <div class="ch-segmented" role="radiogroup" aria-label="<?php esc_attr_e('Tipo de producto', 'cheppi-sellers'); ?>">
                <label><input type="radio" name="type" value="simple" <?php checked(!$isVariable); ?> data-type-switch> <span><?php esc_html_e('Un solo precio', 'cheppi-sellers'); ?></span></label>
                <label><input type="radio" name="type" value="variable" <?php checked($isVariable); ?> data-type-switch> <span><?php esc_html_e('Con opciones (tono, tamaño…)', 'cheppi-sellers'); ?></span></label>
            </div>

            <div class="ch-stack" data-type-panel="simple" <?php echo $isVariable ? 'hidden' : ''; ?>>
                <div class="ch-row">
                    <label class="ch-field">
                        <span class="ch-field__label"><?php esc_html_e('Precio (S/)', 'cheppi-sellers'); ?></span>
                        <input class="ch-input" name="price" inputmode="decimal" value="<?php echo esc_attr($val('price')); ?>"<?php echo $inv('price'); ?>>
                        <?php $err('price'); ?>
                    </label>
                    <label class="ch-field">
                        <span class="ch-field__label"><?php esc_html_e('Precio de oferta', 'cheppi-sellers'); ?> <small><?php esc_html_e('opcional', 'cheppi-sellers'); ?></small></span>
                        <input class="ch-input" name="sale_price" inputmode="decimal" value="<?php echo esc_attr($val('sale_price')); ?>"<?php echo $inv('sale_price'); ?>>
                        <?php $err('sale_price'); ?>
                    </label>
                </div>
                <div class="ch-row">
                    <label class="ch-field">
                        <span class="ch-field__label"><?php esc_html_e('Stock', 'cheppi-sellers'); ?> <small><?php esc_html_e('vacío = sin control', 'cheppi-sellers'); ?></small></span>
                        <input class="ch-input" name="stock" inputmode="numeric" value="<?php echo esc_attr($val('stock')); ?>"<?php echo $inv('stock'); ?>>
                        <?php $err('stock'); ?>
                    </label>
                    <label class="ch-field">
                        <span class="ch-field__label"><?php esc_html_e('Código (SKU)', 'cheppi-sellers'); ?> <small><?php esc_html_e('opcional', 'cheppi-sellers'); ?></small></span>
                        <input class="ch-input" name="sku" maxlength="40" value="<?php echo esc_attr($val('sku')); ?>"<?php echo $inv('sku'); ?>>
                        <?php $err('sku'); ?>
                    </label>
                </div>
            </div>

            <div class="ch-stack" data-type-panel="variable" <?php echo $isVariable ? '' : 'hidden'; ?>>
                <label class="ch-field">
                    <span class="ch-field__label"><?php esc_html_e('¿Qué cambia entre opciones?', 'cheppi-sellers'); ?></span>
                    <input class="ch-input" name="attribute_name" maxlength="30" placeholder="<?php esc_attr_e('Tono', 'cheppi-sellers'); ?>" value="<?php echo esc_attr($val('attribute_name')); ?>"<?php echo $inv('attribute_name'); ?>>
                    <?php $err('attribute_name'); ?>
                </label>
                <div class="ch-table-scroll">
                    <table class="ch-variations" data-repeat="variations">
                        <thead><tr>
                            <th><?php esc_html_e('Opción', 'cheppi-sellers'); ?></th><th><?php esc_html_e('Precio', 'cheppi-sellers'); ?></th><th><?php esc_html_e('Oferta', 'cheppi-sellers'); ?></th><th><?php esc_html_e('Stock', 'cheppi-sellers'); ?></th><th><?php esc_html_e('Código', 'cheppi-sellers'); ?></th><th></th>
                        </tr></thead>
                        <tbody>
                            <?php foreach ($variations as $i => $row) {
                                echo $variationRow($i, (array) $row); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in builder
                            } ?>
                        </tbody>
                    </table>
                </div>
                <template data-template="variations"><?php echo $variationRow('__i__', []); // phpcs:ignore WordPress.Security.EscapeOutput ?></template>
                <?php $err('variations'); ?>
                <div><button type="button" class="ch-btn ch-btn--ghost ch-btn--sm" data-add-row="variations">+ <?php esc_html_e('Añadir opción', 'cheppi-sellers'); ?></button></div>
            </div>
        </section>
    </div>

    <div class="ch-grid ch-sticky">
        <section class="ch-card ch-stack">
            <h2 class="ch-card__title"><?php esc_html_e('Fotos', 'cheppi-sellers'); ?></h2>
            <p class="ch-muted ch-small"><?php esc_html_e('Hasta 8 fotos. La primera es la principal. Fondo claro y producto centrado.', 'cheppi-sellers'); ?></p>
            <?php if ($images !== []) : ?>
                <ul class="ch-thumbs">
                    <?php foreach ($images as $n => $image) : ?>
                        <li>
                            <img src="<?php echo esc_url($image['url']); ?>" alt="" width="96" height="96" loading="lazy" decoding="async">
                            <?php if ($n === 0) : ?><em><?php esc_html_e('Principal', 'cheppi-sellers'); ?></em><?php endif; ?>
                            <label class="ch-thumbs__remove"><input type="checkbox" name="remove_images[]" value="<?php echo (int) $image['id']; ?>"> <span><?php esc_html_e('Quitar', 'cheppi-sellers'); ?></span></label>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <label class="ch-file" data-images>
                <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>
                <span class="ch-file__icon"><?php echo Icons::svg('upload'); ?></span>
                <span class="ch-file__text">
                    <span class="ch-file__name" data-file-name><?php esc_html_e('Agregar fotos', 'cheppi-sellers'); ?></span>
                    <span class="ch-field__help" data-file-help><?php esc_html_e('JPG, PNG o WebP, hasta 5 MB cada una. Las convertimos a WebP.', 'cheppi-sellers'); ?></span>
                </span>
            </label>
            <ul class="ch-thumbs ch-thumbs--preview" data-previews></ul>
            <?php $err('images'); ?>
        </section>

        <section class="ch-card ch-stack">
            <?php if ($moderated && $status !== ProductStatus::Published) : ?>
                <p class="ch-muted ch-small"><?php esc_html_e('El equipo de Cheppi revisa cada producto nuevo antes de publicarlo.', 'cheppi-sellers'); ?></p>
            <?php endif; ?>
            <button type="submit" name="intent" value="publish" class="ch-btn ch-btn--primary"><?php echo esc_html($publishLabel); ?></button>
            <button type="submit" name="intent" value="draft" class="ch-btn ch-btn--ghost" formnovalidate><?php esc_html_e('Guardar borrador', 'cheppi-sellers'); ?></button>
        </section>
    </div>
</form>

<?php if ($productId > 0) : ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ch-delete-row" data-confirm="<?php esc_attr_e('¿Eliminar este producto? Dejará de mostrarse en la tienda.', 'cheppi-sellers'); ?>">
        <input type="hidden" name="action" value="<?php echo esc_attr(PanelHandler::DELETE_PRODUCT); ?>">
        <input type="hidden" name="product_id" value="<?php echo (int) $productId; ?>">
        <?php wp_nonce_field(PanelHandler::DELETE_PRODUCT . $productId); ?>
        <button type="submit" class="ch-btn ch-btn--ghost ch-btn--danger-text"><?php esc_html_e('Eliminar producto', 'cheppi-sellers'); ?></button>
    </form>
<?php endif; ?>
