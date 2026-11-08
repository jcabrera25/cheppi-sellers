<?php
/**
 * Create / edit a product with the options of the WooCommerce editor that matter to a marketplace.
 * Works without JavaScript; the panel module adds the rich text toolbar, the type switch, attribute rows,
 * "generate variations" and photo previews.
 *
 * @var int $productId
 * @var array<string, mixed> $values
 * @var list<array{id: int, url: string}> $images
 * @var array<string, string> $errors
 * @var \Cheppi\Sellers\Domain\Catalog\ProductChoices $choices
 * @var bool $moderated
 * @var string $backUrl    list to go back to
 * @var int $ownerId       0 = the signed-in vendor; >0 = an admin editing for that vendor
 * @var string $ownerName
 */

use Cheppi\Sellers\Domain\Catalog\ProductDraft;
use Cheppi\Sellers\Domain\Catalog\ProductStatus;
use Cheppi\Sellers\Infrastructure\WordPress\Front\Icons;
use Cheppi\Sellers\Infrastructure\WordPress\Front\PanelHandler;

defined('ABSPATH') || exit;

$ownerId = (int) ($ownerId ?? 0);
$val = static fn (string $k): string => isset($values[$k]) && is_scalar($values[$k]) ? (string) $values[$k] : '';
$list = static fn (string $k): array => array_map('strval', is_array($values[$k] ?? null) ? $values[$k] : []);
$on = static fn (string $k): bool => $val($k) !== '' && $val($k) !== '0';
$err = static function (string $k) use ($errors): void {
    printf('<span class="ch-field__error" data-error-for="%s">%s</span>', esc_attr($k), esc_html($errors[$k] ?? ''));
};
$inv = static fn (string $k): string => isset($errors[$k]) ? ' aria-invalid="true"' : '';
$isVariable = $val('type') === 'variable';
$status = ProductStatus::tryFrom($val('status'));
$publishLabel = $status === ProductStatus::Published
    ? __('Guardar cambios', 'cheppi-sellers')
    : ($moderated && $ownerId === 0 ? __('Enviar a revisión', 'cheppi-sellers') : __('Publicar', 'cheppi-sellers'));

$attributes = is_array($values['attributes'] ?? null) ? array_values($values['attributes']) : [];
if ($attributes === []) {
    $attributes[] = ['name' => '', 'options' => '', 'visible' => '1', 'variation' => '1'];
}
$variationAttrs = array_values(array_filter($attributes, static fn ($a): bool => is_array($a) && trim((string) ($a['name'] ?? '')) !== '' && !empty($a['variation'])));
$variations = is_array($values['variations'] ?? null) ? array_values($values['variations']) : [];

/** Rich text: a textarea that the panel script turns into a small editor (bold, italic, lists, link). */
$richText = static function (string $name, string $label, string $help, int $rows) use ($val, $err, $inv): void {
    ?>
    <div class="ch-field">
        <span class="ch-field__label"><?php echo esc_html($label); ?> <small><?php echo esc_html($help); ?></small></span>
        <textarea class="ch-input" name="<?php echo esc_attr($name); ?>" rows="<?php echo (int) $rows; ?>" data-richtext<?php echo $inv($name); ?>><?php echo esc_textarea($val($name)); ?></textarea>
        <?php $err($name); ?>
    </div>
    <?php
};

$attributeRow = static function (int|string $i, array $row) use ($errors): string {
    $v = static fn (string $k): string => esc_attr((string) ($row[$k] ?? ''));
    $e = static fn (string $k): string => isset($errors["attributes.$i.$k"]) ? ' aria-invalid="true"' : '';
    $msg = esc_html(trim(($errors["attributes.$i.name"] ?? '') . ' ' . ($errors["attributes.$i.options"] ?? '')));
    $checked = static fn (string $k, bool $default): string => (isset($row[$k]) ? !empty($row[$k]) && $row[$k] !== '0' : $default) ? ' checked' : '';
    return '<div class="ch-attr" data-attr-row>'
        . '<input class="ch-input" name="attributes[' . $i . '][name]" maxlength="30" value="' . $v('name') . '" placeholder="' . esc_attr__('Atributo (ej. Tono)', 'cheppi-sellers') . '" aria-label="' . esc_attr__('Nombre del atributo', 'cheppi-sellers') . '"' . $e('name') . ' data-attr-name>'
        . '<input class="ch-input" name="attributes[' . $i . '][options]" value="' . $v('options') . '" placeholder="' . esc_attr__('Valores separados por | (ej. Rojo | Nude | Coral)', 'cheppi-sellers') . '" aria-label="' . esc_attr__('Valores', 'cheppi-sellers') . '"' . $e('options') . ' data-attr-options>'
        . '<div class="ch-attr__flags">'
        . '<label class="ch-check"><input type="hidden" name="attributes[' . $i . '][visible]" value="0"><input type="checkbox" name="attributes[' . $i . '][visible]" value="1"' . $checked('visible', true) . '> <span>' . esc_html__('Visible en la ficha', 'cheppi-sellers') . '</span></label>'
        . '<label class="ch-check" data-type-only="variable"><input type="hidden" name="attributes[' . $i . '][variation]" value="0"><input type="checkbox" name="attributes[' . $i . '][variation]" value="1"' . $checked('variation', true) . ' data-attr-variation> <span>' . esc_html__('Usar para variaciones', 'cheppi-sellers') . '</span></label>'
        . '<button type="button" class="ch-icon-btn" data-remove-attr aria-label="' . esc_attr__('Quitar atributo', 'cheppi-sellers') . '">&times;</button>'
        . '</div>'
        . ($msg !== '' ? '<span class="ch-field__error">' . $msg . '</span>' : '')
        . '</div>';
};

$variationRow = static function (int|string $i, array $row) use ($errors, $variationAttrs, $images): string {
    $v = static fn (string $k): string => esc_attr((string) ($row[$k] ?? ''));
    $e = static fn (string $k): string => isset($errors["variations.$i.$k"]) ? ' aria-invalid="true"' : '';
    $chosen = is_array($row['attributes'] ?? null) ? array_values($row['attributes']) : [];
    $selects = '';
    foreach ($variationAttrs as $k => $attr) {
        $options = '<option value="">' . esc_html((string) $attr['name']) . '…</option>';
        foreach (array_filter(array_map('trim', preg_split('/\s*[|,\n]\s*/', (string) $attr['options']) ?: [])) as $option) {
            $options .= '<option value="' . esc_attr($option) . '"' . selected(mb_strtolower((string) ($chosen[$k] ?? '')), mb_strtolower($option), false) . '>' . esc_html($option) . '</option>';
        }
        $selects .= '<select class="ch-input" name="variations[' . $i . '][attributes][' . $k . ']" aria-label="' . esc_attr((string) $attr['name']) . '" data-variation-attr="' . $k . '">' . $options . '</select>';
    }
    $enabled = !isset($row['enabled']) || (!empty($row['enabled']) && $row['enabled'] !== '0');
    $manage = !isset($row['manage_stock']) || (!empty($row['manage_stock']) && $row['manage_stock'] !== '0');
    $statusOptions = '';
    foreach (ProductDraft::STOCK_STATUSES as $key => $label) {
        $statusOptions .= '<option value="' . esc_attr($key) . '"' . selected((string) ($row['stock_status'] ?? 'instock'), $key, false) . '>' . esc_html($label) . '</option>';
    }
    $imageOptions = '<option value="0">' . esc_html__('Foto principal', 'cheppi-sellers') . '</option>';
    foreach ($images as $n => $image) {
        $imageOptions .= '<option value="' . (int) $image['id'] . '"' . selected((string) ($row['image_id'] ?? ''), (string) $image['id'], false) . '>' . esc_html(sprintf(__('Foto %d', 'cheppi-sellers'), $n + 1)) . '</option>';
    }
    $rowErrors = array_filter(array_map(static fn (string $k): string => (string) ($errors["variations.$i.$k"] ?? ''), ['attributes', 'price', 'sale_price', 'sku', 'stock', 'weight', 'description']));
    return '<details class="ch-variation" data-variation-row' . ($rowErrors !== [] || $v('price') === '' ? ' open' : '') . '>'
        . '<summary><span class="ch-variation__combo"' . $e('attributes') . '>' . $selects . '</span>'
        . '<span class="ch-variation__price" data-variation-summary>' . ($v('price') !== '' ? 'S/ ' . $v('price') : esc_html__('Sin precio', 'cheppi-sellers')) . '</span>'
        . '<button type="button" class="ch-icon-btn" data-remove-variation aria-label="' . esc_attr__('Quitar variación', 'cheppi-sellers') . '">&times;</button></summary>'
        . '<input type="hidden" name="variations[' . $i . '][id]" value="' . $v('id') . '">'
        . '<div class="ch-variation__body">'
        . '<label class="ch-check"><input type="hidden" name="variations[' . $i . '][enabled]" value="0"><input type="checkbox" name="variations[' . $i . '][enabled]" value="1"' . ($enabled ? ' checked' : '') . '> <span>' . esc_html__('Activa (se puede comprar)', 'cheppi-sellers') . '</span></label>'
        . '<div class="ch-row ch-row--4">'
        . '<label class="ch-field"><span class="ch-field__label">' . esc_html__('Precio S/', 'cheppi-sellers') . '</span><input class="ch-input" name="variations[' . $i . '][price]" inputmode="decimal" value="' . $v('price') . '"' . $e('price') . '></label>'
        . '<label class="ch-field"><span class="ch-field__label">' . esc_html__('Oferta S/', 'cheppi-sellers') . '</span><input class="ch-input" name="variations[' . $i . '][sale_price]" inputmode="decimal" value="' . $v('sale_price') . '"' . $e('sale_price') . '></label>'
        . '<label class="ch-field"><span class="ch-field__label">SKU</span><input class="ch-input" name="variations[' . $i . '][sku]" maxlength="40" value="' . $v('sku') . '"' . $e('sku') . '></label>'
        . '<label class="ch-field"><span class="ch-field__label">' . esc_html__('Peso kg', 'cheppi-sellers') . '</span><input class="ch-input" name="variations[' . $i . '][weight]" inputmode="decimal" value="' . $v('weight') . '"' . $e('weight') . '></label>'
        . '</div>'
        . '<div class="ch-row ch-row--4">'
        . '<label class="ch-check ch-check--field"><input type="hidden" name="variations[' . $i . '][manage_stock]" value="0"><input type="checkbox" name="variations[' . $i . '][manage_stock]" value="1"' . ($manage ? ' checked' : '') . ' data-toggle-next> <span>' . esc_html__('Controlar stock', 'cheppi-sellers') . '</span></label>'
        . '<label class="ch-field" data-when-manage><span class="ch-field__label">' . esc_html__('Stock', 'cheppi-sellers') . '</span><input class="ch-input" name="variations[' . $i . '][stock]" inputmode="numeric" value="' . $v('stock') . '"' . $e('stock') . '></label>'
        . '<label class="ch-field" data-when-no-manage><span class="ch-field__label">' . esc_html__('Estado', 'cheppi-sellers') . '</span><select class="ch-input" name="variations[' . $i . '][stock_status]">' . $statusOptions . '</select></label>'
        . '<label class="ch-field"><span class="ch-field__label">' . esc_html__('Foto', 'cheppi-sellers') . '</span><select class="ch-input" name="variations[' . $i . '][image_id]">' . $imageOptions . '</select></label>'
        . '</div>'
        . '<label class="ch-field"><span class="ch-field__label">' . esc_html__('Descripción de la variación', 'cheppi-sellers') . ' <small>' . esc_html__('opcional', 'cheppi-sellers') . '</small></span><input class="ch-input" name="variations[' . $i . '][description]" maxlength="500" value="' . $v('description') . '"></label>'
        . ($rowErrors !== [] ? '<span class="ch-field__error">' . esc_html(implode(' ', $rowErrors)) . '</span>' : '')
        . '</div></details>';
};

$checkList = static function (string $name, array $options, array $selected): void {
    if ($options === []) {
        echo '<p class="ch-muted ch-small">' . esc_html__('No hay opciones todavía.', 'cheppi-sellers') . '</p>';
        return;
    }
    echo '<div class="ch-checklist">';
    foreach ($options as $id => $label) {
        printf(
            '<label class="ch-check"><input type="checkbox" name="%s[]" value="%d"%s> <span>%s</span></label>',
            esc_attr($name),
            (int) $id,
            in_array((string) $id, $selected, true) ? ' checked' : '',
            esc_html((string) $label)
        );
    }
    echo '</div>';
};
?>
<p class="ch-back"><a href="<?php echo esc_url($backUrl); ?>">&larr; <?php esc_html_e('Productos', 'cheppi-sellers'); ?></a>
    <?php if ($ownerId > 0) : ?> · <?php echo esc_html(sprintf(__('Tienda: %s', 'cheppi-sellers'), $ownerName)); ?><?php endif; ?>
    <?php if ($status !== null) : ?> · <span class="ch-chip <?php echo $status === ProductStatus::Published ? 'ch-chip--success' : ($status === ProductStatus::Pending ? 'ch-chip--warning' : 'ch-chip--neutral'); ?>"><?php echo esc_html($status->label()); ?></span><?php endif; ?>
    <?php if ($status === ProductStatus::Published && !empty($values['permalink'])) : ?> · <a href="<?php echo esc_url((string) $values['permalink']); ?>" target="_blank" rel="noopener"><?php esc_html_e('Ver en la tienda', 'cheppi-sellers'); ?></a><?php endif; ?>
</p>

<form class="ch-grid ch-grid--2" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-product-form>
    <input type="hidden" name="action" value="<?php echo esc_attr(PanelHandler::SAVE_PRODUCT); ?>">
    <input type="hidden" name="product_id" value="<?php echo (int) $productId; ?>">
    <?php if ($ownerId > 0) : ?><input type="hidden" name="owner_id" value="<?php echo (int) $ownerId; ?>"><?php endif; ?>
    <?php wp_nonce_field(PanelHandler::SAVE_PRODUCT . $productId); ?>

    <div class="ch-grid">
        <section class="ch-card ch-stack">
            <div class="ch-segmented" role="radiogroup" aria-label="<?php esc_attr_e('Tipo de producto', 'cheppi-sellers'); ?>">
                <label><input type="radio" name="type" value="simple" <?php checked(!$isVariable); ?> data-type-switch> <span><?php esc_html_e('Producto simple', 'cheppi-sellers'); ?></span></label>
                <label><input type="radio" name="type" value="variable" <?php checked($isVariable); ?> data-type-switch> <span><?php esc_html_e('Producto variable (tonos, tamaños…)', 'cheppi-sellers'); ?></span></label>
            </div>
            <label class="ch-field">
                <span class="ch-field__label"><?php esc_html_e('Nombre del producto', 'cheppi-sellers'); ?></span>
                <input class="ch-input" name="name" required minlength="3" maxlength="120" value="<?php echo esc_attr($val('name')); ?>"<?php echo $inv('name'); ?>>
                <?php $err('name'); ?>
            </label>
            <?php $richText('short_description', __('Descripción corta', 'cheppi-sellers'), __('se ve junto al precio', 'cheppi-sellers'), 3); ?>
            <?php $richText('description', __('Descripción', 'cheppi-sellers'), __('qué es, cómo se usa, ingredientes, contenido neto', 'cheppi-sellers'), 7); ?>
        </section>

        <section class="ch-card ch-stack" data-type-only="simple">
            <div class="ch-card__head"><h2 class="ch-card__title"><?php esc_html_e('Precio', 'cheppi-sellers'); ?></h2>
                <label class="ch-check"><input type="checkbox" name="virtual" value="1" <?php checked($on('virtual')); ?> data-virtual> <span><?php esc_html_e('Virtual (servicio, sin envío)', 'cheppi-sellers'); ?></span></label></div>
            <div class="ch-row">
                <label class="ch-field">
                    <span class="ch-field__label"><?php esc_html_e('Precio (S/)', 'cheppi-sellers'); ?></span>
                    <input class="ch-input" name="price" inputmode="decimal" value="<?php echo esc_attr($val('price')); ?>"<?php echo $inv('price'); ?>>
                    <?php $err('price'); ?>
                </label>
                <label class="ch-field">
                    <span class="ch-field__label"><?php esc_html_e('Precio de oferta (S/)', 'cheppi-sellers'); ?> <small><?php esc_html_e('opcional', 'cheppi-sellers'); ?></small></span>
                    <input class="ch-input" name="sale_price" inputmode="decimal" value="<?php echo esc_attr($val('sale_price')); ?>"<?php echo $inv('sale_price'); ?>>
                    <?php $err('sale_price'); ?>
                </label>
            </div>
            <details class="ch-more" <?php echo $val('sale_from') !== '' || $val('sale_to') !== '' || isset($errors['sale_from']) ? 'open' : ''; ?>>
                <summary><?php esc_html_e('Programar la oferta', 'cheppi-sellers'); ?></summary>
                <div class="ch-row">
                    <label class="ch-field"><span class="ch-field__label"><?php esc_html_e('Desde', 'cheppi-sellers'); ?></span><input class="ch-input" type="date" name="sale_from" value="<?php echo esc_attr($val('sale_from')); ?>"></label>
                    <label class="ch-field"><span class="ch-field__label"><?php esc_html_e('Hasta', 'cheppi-sellers'); ?></span><input class="ch-input" type="date" name="sale_to" value="<?php echo esc_attr($val('sale_to')); ?>"></label>
                </div>
                <?php $err('sale_from'); ?>
            </details>
        </section>

        <section class="ch-card ch-stack">
            <h2 class="ch-card__title"><?php esc_html_e('Inventario', 'cheppi-sellers'); ?></h2>
            <div class="ch-row">
                <label class="ch-field">
                    <span class="ch-field__label">SKU <small><?php esc_html_e('código único, opcional', 'cheppi-sellers'); ?></small></span>
                    <input class="ch-input" name="sku" maxlength="40" value="<?php echo esc_attr($val('sku')); ?>"<?php echo $inv('sku'); ?>>
                    <?php $err('sku'); ?>
                </label>
                <label class="ch-check ch-check--field" data-type-only="simple"><input type="checkbox" name="manage_stock" value="1" <?php checked($on('manage_stock')); ?> data-toggle-next> <span><?php esc_html_e('¿Gestionar inventario?', 'cheppi-sellers'); ?></span></label>
            </div>
            <p class="ch-muted ch-small" data-type-only="variable"><?php esc_html_e('El stock y su estado se definen en cada variación.', 'cheppi-sellers'); ?></p>
            <div class="ch-row ch-row--3" data-when-manage data-type-only="simple">
                <label class="ch-field">
                    <span class="ch-field__label"><?php esc_html_e('Cantidad en stock', 'cheppi-sellers'); ?></span>
                    <input class="ch-input" name="stock" inputmode="numeric" value="<?php echo esc_attr($val('stock')); ?>"<?php echo $inv('stock'); ?>>
                    <?php $err('stock'); ?>
                </label>
                <label class="ch-field">
                    <span class="ch-field__label"><?php esc_html_e('¿Permitir reservas?', 'cheppi-sellers'); ?></span>
                    <select class="ch-input" name="backorders"><?php foreach (ProductDraft::BACKORDERS as $k => $label) : ?><option value="<?php echo esc_attr($k); ?>" <?php selected($val('backorders') ?: 'no', $k); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select>
                </label>
                <label class="ch-field">
                    <span class="ch-field__label"><?php esc_html_e('Aviso de stock bajo', 'cheppi-sellers'); ?></span>
                    <input class="ch-input" name="low_stock" inputmode="numeric" value="<?php echo esc_attr($val('low_stock')); ?>" placeholder="<?php esc_attr_e('Ej. 3', 'cheppi-sellers'); ?>"<?php echo $inv('low_stock'); ?>>
                    <?php $err('low_stock'); ?>
                </label>
            </div>
            <label class="ch-field" data-when-no-manage data-type-only="simple">
                <span class="ch-field__label"><?php esc_html_e('Estado del inventario', 'cheppi-sellers'); ?></span>
                <select class="ch-input" name="stock_status"><?php foreach (ProductDraft::STOCK_STATUSES as $k => $label) : ?><option value="<?php echo esc_attr($k); ?>" <?php selected($val('stock_status') ?: 'instock', $k); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select>
            </label>
            <label class="ch-check"><input type="checkbox" name="sold_individually" value="1" <?php checked($on('sold_individually')); ?>> <span><?php esc_html_e('Vendido individualmente (máximo 1 por pedido)', 'cheppi-sellers'); ?></span></label>
        </section>

        <section class="ch-card ch-stack" data-shipping-card>
            <h2 class="ch-card__title"><?php esc_html_e('Envío', 'cheppi-sellers'); ?></h2>
            <div class="ch-row ch-row--4">
                <label class="ch-field"><span class="ch-field__label"><?php esc_html_e('Peso (kg)', 'cheppi-sellers'); ?></span><input class="ch-input" name="weight" inputmode="decimal" value="<?php echo esc_attr($val('weight')); ?>"<?php echo $inv('weight'); ?>></label>
                <label class="ch-field"><span class="ch-field__label"><?php esc_html_e('Largo (cm)', 'cheppi-sellers'); ?></span><input class="ch-input" name="length" inputmode="decimal" value="<?php echo esc_attr($val('length')); ?>"></label>
                <label class="ch-field"><span class="ch-field__label"><?php esc_html_e('Ancho (cm)', 'cheppi-sellers'); ?></span><input class="ch-input" name="width" inputmode="decimal" value="<?php echo esc_attr($val('width')); ?>"></label>
                <label class="ch-field"><span class="ch-field__label"><?php esc_html_e('Alto (cm)', 'cheppi-sellers'); ?></span><input class="ch-input" name="height" inputmode="decimal" value="<?php echo esc_attr($val('height')); ?>"></label>
            </div>
            <?php $err('weight'); ?><?php $err('dimensions'); ?>
            <p class="ch-muted ch-small"><?php esc_html_e('Sirve para cotizar envíos por courier. Tu delivery propio se configura en Envíos.', 'cheppi-sellers'); ?></p>
        </section>

        <section class="ch-card ch-stack">
            <h2 class="ch-card__title"><?php esc_html_e('Atributos', 'cheppi-sellers'); ?></h2>
            <p class="ch-muted ch-small" data-type-only="simple"><?php esc_html_e('Datos que se muestran en la ficha, por ejemplo «Acabado: Mate» o «Tipo de piel: Grasa | Mixta».', 'cheppi-sellers'); ?></p>
            <p class="ch-muted ch-small" data-type-only="variable"><?php esc_html_e('Marca «Usar para variaciones» en lo que el cliente elige (Tono, Tamaño). Los demás se muestran como información.', 'cheppi-sellers'); ?></p>
            <div class="ch-stack" data-attr-list>
                <?php foreach ($attributes as $i => $row) {
                    echo $attributeRow($i, (array) $row); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in builder
                } ?>
            </div>
            <template data-template="attribute"><?php echo $attributeRow('__i__', ['visible' => '1', 'variation' => '1']); // phpcs:ignore WordPress.Security.EscapeOutput ?></template>
            <?php $err('attributes'); ?>
            <div><button type="button" class="ch-btn ch-btn--ghost ch-btn--sm" data-add-attr>+ <?php esc_html_e('Añadir atributo', 'cheppi-sellers'); ?></button></div>
        </section>

        <section class="ch-card ch-stack" data-type-only="variable">
            <div class="ch-card__head">
                <h2 class="ch-card__title"><?php esc_html_e('Variaciones', 'cheppi-sellers'); ?></h2>
                <span class="ch-actions">
                    <button type="button" class="ch-btn ch-btn--secondary ch-btn--sm" data-generate-variations><?php esc_html_e('Generar variaciones', 'cheppi-sellers'); ?></button>
                    <button type="button" class="ch-btn ch-btn--ghost ch-btn--sm" data-add-variation>+ <?php esc_html_e('Una', 'cheppi-sellers'); ?></button>
                </span>
            </div>
            <p class="ch-muted ch-small"><?php echo esc_html(sprintf(__('Crea una variación por cada combinación de atributos (máximo %d). Para aplicar el mismo precio a todas, usa «Precio para todas».', 'cheppi-sellers'), ProductDraft::MAX_VARIATIONS)); ?></p>
            <div class="ch-row ch-bulk">
                <input class="ch-input" inputmode="decimal" placeholder="<?php esc_attr_e('Precio para todas (S/)', 'cheppi-sellers'); ?>" data-bulk-price aria-label="<?php esc_attr_e('Precio para todas', 'cheppi-sellers'); ?>">
                <input class="ch-input" inputmode="numeric" placeholder="<?php esc_attr_e('Stock para todas', 'cheppi-sellers'); ?>" data-bulk-stock aria-label="<?php esc_attr_e('Stock para todas', 'cheppi-sellers'); ?>">
                <button type="button" class="ch-btn ch-btn--ghost ch-btn--sm" data-bulk-apply><?php esc_html_e('Aplicar', 'cheppi-sellers'); ?></button>
            </div>
            <div class="ch-stack" data-variation-list>
                <?php foreach ($variations as $i => $row) {
                    echo $variationRow($i, (array) $row); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in builder
                } ?>
            </div>
            <template data-template="variation"><?php echo $variationRow('__i__', []); // phpcs:ignore WordPress.Security.EscapeOutput ?></template>
            <?php $err('variations'); ?>
        </section>

        <section class="ch-card ch-stack">
            <h2 class="ch-card__title"><?php esc_html_e('Productos vinculados', 'cheppi-sellers'); ?></h2>
            <?php if ($choices->linkable === []) : ?>
                <p class="ch-muted ch-small"><?php esc_html_e('Cuando tengas más productos podrás recomendarlos aquí.', 'cheppi-sellers'); ?></p>
            <?php else : ?>
                <div class="ch-row">
                    <label class="ch-field"><span class="ch-field__label"><?php esc_html_e('Ventas dirigidas', 'cheppi-sellers'); ?> <small><?php esc_html_e('se sugieren en la ficha', 'cheppi-sellers'); ?></small></span>
                        <select class="ch-input" name="upsells[]" multiple size="5"><?php foreach ($choices->linkable as $id => $label) : ?><option value="<?php echo (int) $id; ?>" <?php echo in_array((string) $id, $list('upsells'), true) ? 'selected' : ''; ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                    <label class="ch-field"><span class="ch-field__label"><?php esc_html_e('Ventas cruzadas', 'cheppi-sellers'); ?> <small><?php esc_html_e('se sugieren en el carrito', 'cheppi-sellers'); ?></small></span>
                        <select class="ch-input" name="cross_sells[]" multiple size="5"><?php foreach ($choices->linkable as $id => $label) : ?><option value="<?php echo (int) $id; ?>" <?php echo in_array((string) $id, $list('cross_sells'), true) ? 'selected' : ''; ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                </div>
                <p class="ch-muted ch-small"><?php esc_html_e('Ctrl/Cmd + clic para elegir varios.', 'cheppi-sellers'); ?></p>
            <?php endif; ?>
            <label class="ch-field">
                <span class="ch-field__label"><?php esc_html_e('Nota de compra', 'cheppi-sellers'); ?> <small><?php esc_html_e('se envía al cliente después de comprar', 'cheppi-sellers'); ?></small></span>
                <textarea class="ch-input" name="purchase_note" rows="2" maxlength="500"<?php echo $inv('purchase_note'); ?>><?php echo esc_textarea($val('purchase_note')); ?></textarea>
                <?php $err('purchase_note'); ?>
            </label>
        </section>
    </div>

    <div class="ch-grid ch-sticky">
        <section class="ch-card ch-stack">
            <?php if ($moderated && $ownerId === 0 && $status !== ProductStatus::Published) : ?>
                <p class="ch-muted ch-small"><?php esc_html_e('El equipo de Cheppi revisa cada producto nuevo antes de publicarlo.', 'cheppi-sellers'); ?></p>
            <?php endif; ?>
            <?php if (isset($errors['form'])) : ?><div class="ch-alert ch-alert--danger"><p><?php echo esc_html($errors['form']); ?></p></div><?php endif; ?>
            <button type="submit" name="intent" value="publish" class="ch-btn ch-btn--primary"><?php echo esc_html($publishLabel); ?></button>
            <button type="submit" name="intent" value="draft" class="ch-btn ch-btn--ghost" formnovalidate><?php esc_html_e('Guardar borrador', 'cheppi-sellers'); ?></button>
        </section>

        <section class="ch-card ch-stack">
            <h2 class="ch-card__title"><?php esc_html_e('Fotos', 'cheppi-sellers'); ?></h2>
            <p class="ch-muted ch-small"><?php esc_html_e('Hasta 8 fotos. La primera es la principal. Fondo claro y producto centrado.', 'cheppi-sellers'); ?></p>
            <?php if ($images !== []) : ?>
                <ul class="ch-thumbs">
                    <?php foreach ($images as $n => $image) : ?>
                        <li>
                            <img src="<?php echo esc_url($image['url']); ?>" alt="" width="96" height="96" loading="lazy" decoding="async">
                            <em><?php echo $n === 0 ? esc_html__('Principal', 'cheppi-sellers') : esc_html(sprintf(__('Foto %d', 'cheppi-sellers'), $n + 1)); ?></em>
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
                    <span class="ch-field__help" data-file-help><?php esc_html_e('JPG, PNG o WebP, hasta 5 MB cada una.', 'cheppi-sellers'); ?></span>
                </span>
            </label>
            <ul class="ch-thumbs ch-thumbs--preview" data-previews></ul>
            <?php $err('images'); ?>
            <p class="ch-muted ch-small" data-type-only="variable"><?php esc_html_e('Después de guardar podrás asignar una foto a cada variación.', 'cheppi-sellers'); ?></p>
        </section>

        <section class="ch-card ch-stack">
            <h2 class="ch-card__title"><?php esc_html_e('Categorías', 'cheppi-sellers'); ?></h2>
            <?php $checkList('categories', $choices->categories, $list('categories')); ?>
            <?php $err('categories'); ?>
        </section>

        <?php if ($choices->brands !== []) : ?>
            <section class="ch-card ch-stack">
                <h2 class="ch-card__title"><?php esc_html_e('Marcas de productos', 'cheppi-sellers'); ?></h2>
                <?php $checkList('brands', $choices->brands, $list('brands')); ?>
            </section>
        <?php endif; ?>

        <section class="ch-card ch-stack">
            <label class="ch-field">
                <span class="ch-card__title"><?php esc_html_e('Etiquetas', 'cheppi-sellers'); ?></span>
                <input class="ch-input" name="tags" value="<?php echo esc_attr($val('tags')); ?>" placeholder="<?php esc_attr_e('vegano, cruelty free, piel grasa', 'cheppi-sellers'); ?>"<?php echo $inv('tags'); ?>>
                <span class="ch-field__help"><?php esc_html_e('Separa las etiquetas con comas.', 'cheppi-sellers'); ?></span>
                <?php $err('tags'); ?>
            </label>
        </section>
    </div>
</form>

<?php if ($productId > 0) : ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ch-delete-row" data-confirm="<?php esc_attr_e('¿Eliminar este producto? Dejará de mostrarse en la tienda.', 'cheppi-sellers'); ?>">
        <input type="hidden" name="action" value="<?php echo esc_attr(PanelHandler::DELETE_PRODUCT); ?>">
        <input type="hidden" name="product_id" value="<?php echo (int) $productId; ?>">
        <?php if ($ownerId > 0) : ?><input type="hidden" name="owner_id" value="<?php echo (int) $ownerId; ?>"><?php endif; ?>
        <?php wp_nonce_field(PanelHandler::DELETE_PRODUCT . $productId); ?>
        <button type="submit" class="ch-btn ch-btn--ghost ch-btn--danger-text"><?php esc_html_e('Eliminar producto', 'cheppi-sellers'); ?></button>
    </form>
<?php endif; ?>
