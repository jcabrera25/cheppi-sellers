<?php
/**
 * Business rules screen.
 *
 * @var array<string, mixed>  $form
 * @var array<string, string> $errors
 * @var bool                  $saved
 * @var array<int, string>    $categories term id => name
 * @var list<array{changed_at: string, changed_by: int, rules: array<string, mixed>}> $history
 * @var string                $action
 * @var string                $nonce
 */

defined('ABSPATH') || exit;

$fieldError = static function (string $key) use ($errors): void {
    if (isset($errors[$key])) {
        printf('<p class="ch-field__error" role="alert">%s</p>', esc_html($errors[$key]));
    }
};
$brand = (array) ($form['brand_page'] ?? []);
$periods = [
    'none' => __('Sin renovación', 'cheppi-sellers'),
    'monthly' => __('Mensual', 'cheppi-sellers'),
    'yearly' => __('Anual', 'cheppi-sellers'),
];
?>
<div class="wrap ch-admin">
    <header class="ch-admin__head">
        <h1><?php esc_html_e('Reglas de negocio', 'cheppi-sellers'); ?></h1>
        <p class="ch-muted">
            <?php esc_html_e('Comisiones, planes, plazos y precios del marketplace. Cada cambio queda registrado y no afecta pedidos ya realizados.', 'cheppi-sellers'); ?>
        </p>
    </header>

    <?php if ($saved) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Reglas guardadas.', 'cheppi-sellers'); ?></p></div>
    <?php endif; ?>
    <?php if ($errors !== []) : ?>
        <div class="notice notice-error">
            <p><strong><?php esc_html_e('No se guardó: revisa estos campos.', 'cheppi-sellers'); ?></strong></p>
            <ul class="ch-admin__errors">
                <?php foreach ($errors as $message) : ?>
                    <li><?php echo esc_html($message); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ch-admin__form">
        <input type="hidden" name="action" value="<?php echo esc_attr($action); ?>">
        <?php wp_nonce_field($action, $nonce); ?>

        <section class="ch-card">
            <h2 class="ch-card__title"><?php esc_html_e('Comisión general', 'cheppi-sellers'); ?></h2>
            <p class="ch-muted ch-card__lead"><?php esc_html_e('Se usa cuando no aplica una comisión de vendedor, de categoría ni de plan.', 'cheppi-sellers'); ?></p>
            <label class="ch-field ch-field--short">
                <span class="ch-field__label"><?php esc_html_e('Comisión (%)', 'cheppi-sellers'); ?></span>
                <input class="ch-input" type="text" inputmode="decimal" name="rules[default_commission_percent]" value="<?php echo esc_attr((string) $form['default_commission_percent']); ?>" required>
                <?php $fieldError('default_commission_bps'); ?>
            </label>
        </section>

        <section class="ch-card">
            <h2 class="ch-card__title"><?php esc_html_e('Planes de vendedor', 'cheppi-sellers'); ?></h2>
            <p class="ch-muted ch-card__lead"><?php esc_html_e('Deja el límite de productos vacío para ilimitado.', 'cheppi-sellers'); ?></p>
            <?php $fieldError('plans'); ?>
            <table class="ch-table-clean ch-table-edit" data-repeat="plans">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Código', 'cheppi-sellers'); ?></th>
                        <th><?php esc_html_e('Nombre', 'cheppi-sellers'); ?></th>
                        <th><?php esc_html_e('Mensualidad (S/)', 'cheppi-sellers'); ?></th>
                        <th><?php esc_html_e('Límite de productos', 'cheppi-sellers'); ?></th>
                        <th><?php esc_html_e('Comisión (%)', 'cheppi-sellers'); ?></th>
                        <th><span class="screen-reader-text"><?php esc_html_e('Quitar', 'cheppi-sellers'); ?></span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ((array) $form['plans'] as $i => $plan) : ?>
                        <tr>
                            <td><input class="ch-input" type="text" name="rules[plans][<?php echo (int) $i; ?>][id]" value="<?php echo esc_attr((string) $plan['id']); ?>" pattern="[a-z0-9_\-]{2,32}" aria-label="<?php esc_attr_e('Código', 'cheppi-sellers'); ?>"></td>
                            <td><input class="ch-input" type="text" name="rules[plans][<?php echo (int) $i; ?>][name]" value="<?php echo esc_attr((string) $plan['name']); ?>" aria-label="<?php esc_attr_e('Nombre', 'cheppi-sellers'); ?>"></td>
                            <td><input class="ch-input" type="text" inputmode="decimal" name="rules[plans][<?php echo (int) $i; ?>][monthly_fee]" value="<?php echo esc_attr((string) $plan['monthly_fee']); ?>" aria-label="<?php esc_attr_e('Mensualidad', 'cheppi-sellers'); ?>"></td>
                            <td><input class="ch-input" type="text" inputmode="numeric" name="rules[plans][<?php echo (int) $i; ?>][product_limit]" value="<?php echo esc_attr((string) $plan['product_limit']); ?>" aria-label="<?php esc_attr_e('Límite de productos', 'cheppi-sellers'); ?>"></td>
                            <td><input class="ch-input" type="text" inputmode="decimal" name="rules[plans][<?php echo (int) $i; ?>][commission_percent]" value="<?php echo esc_attr((string) $plan['commission_percent']); ?>" aria-label="<?php esc_attr_e('Comisión', 'cheppi-sellers'); ?>"></td>
                            <td><button type="button" class="ch-icon-btn" data-remove-row aria-label="<?php esc_attr_e('Quitar plan', 'cheppi-sellers'); ?>">&times;</button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <template data-template="plans">
                <tr>
                    <td><input class="ch-input" type="text" data-name="rules[plans][__i__][id]" pattern="[a-z0-9_\-]{2,32}" aria-label="<?php esc_attr_e('Código', 'cheppi-sellers'); ?>"></td>
                    <td><input class="ch-input" type="text" data-name="rules[plans][__i__][name]" aria-label="<?php esc_attr_e('Nombre', 'cheppi-sellers'); ?>"></td>
                    <td><input class="ch-input" type="text" inputmode="decimal" data-name="rules[plans][__i__][monthly_fee]" value="0.00" aria-label="<?php esc_attr_e('Mensualidad', 'cheppi-sellers'); ?>"></td>
                    <td><input class="ch-input" type="text" inputmode="numeric" data-name="rules[plans][__i__][product_limit]" aria-label="<?php esc_attr_e('Límite de productos', 'cheppi-sellers'); ?>"></td>
                    <td><input class="ch-input" type="text" inputmode="decimal" data-name="rules[plans][__i__][commission_percent]" aria-label="<?php esc_attr_e('Comisión', 'cheppi-sellers'); ?>"></td>
                    <td><button type="button" class="ch-icon-btn" data-remove-row aria-label="<?php esc_attr_e('Quitar plan', 'cheppi-sellers'); ?>">&times;</button></td>
                </tr>
            </template>
            <button type="button" class="ch-btn ch-btn--ghost ch-btn--sm" data-add-row="plans"><?php esc_html_e('Añadir plan', 'cheppi-sellers'); ?></button>

            <label class="ch-field ch-field--short">
                <span class="ch-field__label"><?php esc_html_e('Plan para vendedores nuevos (código)', 'cheppi-sellers'); ?></span>
                <input class="ch-input" type="text" name="rules[default_plan_id]" value="<?php echo esc_attr((string) $form['default_plan_id']); ?>" required>
                <?php $fieldError('default_plan_id'); ?>
            </label>
        </section>

        <section class="ch-card">
            <h2 class="ch-card__title"><?php esc_html_e('Comisión por categoría', 'cheppi-sellers'); ?></h2>
            <p class="ch-muted ch-card__lead"><?php esc_html_e('Reemplaza la comisión del plan. Si un producto está en varias categorías, se usa la más baja.', 'cheppi-sellers'); ?></p>
            <table class="ch-table-clean ch-table-edit" data-repeat="categories">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Categoría', 'cheppi-sellers'); ?></th>
                        <th><?php esc_html_e('Comisión (%)', 'cheppi-sellers'); ?></th>
                        <th><span class="screen-reader-text"><?php esc_html_e('Quitar', 'cheppi-sellers'); ?></span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ((array) $form['categories'] as $i => $row) : ?>
                        <tr>
                            <td>
                                <select class="ch-input" name="rules[categories][<?php echo (int) $i; ?>][term_id]" aria-label="<?php esc_attr_e('Categoría', 'cheppi-sellers'); ?>">
                                    <?php foreach ($categories as $termId => $termName) : ?>
                                        <option value="<?php echo (int) $termId; ?>" <?php selected((string) $termId, (string) $row['term_id']); ?>><?php echo esc_html($termName); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input class="ch-input" type="text" inputmode="decimal" name="rules[categories][<?php echo (int) $i; ?>][commission_percent]" value="<?php echo esc_attr((string) $row['commission_percent']); ?>" aria-label="<?php esc_attr_e('Comisión', 'cheppi-sellers'); ?>"></td>
                            <td><button type="button" class="ch-icon-btn" data-remove-row aria-label="<?php esc_attr_e('Quitar categoría', 'cheppi-sellers'); ?>">&times;</button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <template data-template="categories">
                <tr>
                    <td>
                        <select class="ch-input" data-name="rules[categories][__i__][term_id]" aria-label="<?php esc_attr_e('Categoría', 'cheppi-sellers'); ?>">
                            <?php foreach ($categories as $termId => $termName) : ?>
                                <option value="<?php echo (int) $termId; ?>"><?php echo esc_html($termName); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input class="ch-input" type="text" inputmode="decimal" data-name="rules[categories][__i__][commission_percent]" aria-label="<?php esc_attr_e('Comisión', 'cheppi-sellers'); ?>"></td>
                    <td><button type="button" class="ch-icon-btn" data-remove-row aria-label="<?php esc_attr_e('Quitar categoría', 'cheppi-sellers'); ?>">&times;</button></td>
                </tr>
            </template>
            <?php if ($categories === []) : ?>
                <p class="ch-muted ch-card__lead"><?php esc_html_e('Aún no hay categorías de producto en WooCommerce.', 'cheppi-sellers'); ?></p>
            <?php else : ?>
                <button type="button" class="ch-btn ch-btn--ghost ch-btn--sm" data-add-row="categories"><?php esc_html_e('Añadir categoría', 'cheppi-sellers'); ?></button>
            <?php endif; ?>
        </section>

        <section class="ch-card">
            <h2 class="ch-card__title"><?php esc_html_e('Plazos y liquidaciones', 'cheppi-sellers'); ?></h2>
            <div class="ch-form-grid">
                <label class="ch-field">
                    <span class="ch-field__label"><?php esc_html_e('Plazo de despacho (horas)', 'cheppi-sellers'); ?></span>
                    <input class="ch-input" type="number" min="1" max="720" name="rules[dispatch_hours]" value="<?php echo esc_attr((string) $form['dispatch_hours']); ?>" required>
                    <?php $fieldError('dispatch_hours'); ?>
                </label>
                <label class="ch-field">
                    <span class="ch-field__label"><?php esc_html_e('Plazo de devolución (días)', 'cheppi-sellers'); ?></span>
                    <input class="ch-input" type="number" min="0" max="90" name="rules[return_days]" value="<?php echo esc_attr((string) $form['return_days']); ?>" required>
                    <?php $fieldError('return_days'); ?>
                </label>
                <label class="ch-field">
                    <span class="ch-field__label"><?php esc_html_e('Días de corte del mes', 'cheppi-sellers'); ?></span>
                    <input class="ch-input" type="text" name="rules[payout_days]" value="<?php echo esc_attr((string) $form['payout_days']); ?>" placeholder="15, 31" required>
                    <small class="ch-field__help"><?php esc_html_e('Separados por coma. 31 = último día de cada mes.', 'cheppi-sellers'); ?></small>
                    <?php $fieldError('payout_days'); ?>
                </label>
                <label class="ch-field">
                    <span class="ch-field__label"><?php esc_html_e('Monto mínimo a liquidar (S/)', 'cheppi-sellers'); ?></span>
                    <input class="ch-input" type="text" inputmode="decimal" name="rules[minimum_payout]" value="<?php echo esc_attr((string) $form['minimum_payout']); ?>" required>
                    <?php $fieldError('minimum_payout'); ?>
                </label>
            </div>
        </section>

        <section class="ch-card">
            <h2 class="ch-card__title"><?php esc_html_e('Productos', 'cheppi-sellers'); ?></h2>
            <label class="ch-check">
                <input type="checkbox" name="rules[moderate_products]" value="1" <?php checked(!empty($form['moderate_products'])); ?>>
                <span><?php esc_html_e('Revisar cada producto nuevo antes de publicarlo', 'cheppi-sellers'); ?></span>
            </label>
            <p class="ch-muted ch-card__lead"><?php esc_html_e('Si lo desactivas, los productos de vendedores aprobados se publican al instante.', 'cheppi-sellers'); ?></p>
            <label class="ch-field" style="max-width:240px">
                <span class="ch-field__label"><?php esc_html_e('Producto destacado: precio por día (S/)', 'cheppi-sellers'); ?></span>
                <input class="ch-input" type="text" inputmode="decimal" name="rules[featured_per_day]" value="<?php echo esc_attr((string) ($form['featured_per_day'] ?? '5.00')); ?>">
                <?php $fieldError('featured_per_day'); ?>
            </label>
        </section>

        <section class="ch-card">
            <h2 class="ch-card__title"><?php esc_html_e('Página de marca premium', 'cheppi-sellers'); ?></h2>
            <p class="ch-muted ch-card__lead"><?php esc_html_e('Servicio opcional con costo: página interna con diseño personalizado para la marca.', 'cheppi-sellers'); ?></p>
            <label class="ch-check">
                <input type="checkbox" name="rules[brand_page][enabled]" value="1" <?php checked(!empty($brand['enabled'])); ?>>
                <?php esc_html_e('Ofrecer el servicio a los vendedores', 'cheppi-sellers'); ?>
            </label>
            <div class="ch-form-grid">
                <label class="ch-field">
                    <span class="ch-field__label"><?php esc_html_e('Precio de diseño (S/)', 'cheppi-sellers'); ?></span>
                    <input class="ch-input" type="text" inputmode="decimal" name="rules[brand_page][design_fee]" value="<?php echo esc_attr((string) ($brand['design_fee'] ?? '')); ?>">
                    <?php $fieldError('brand_page.design_fee'); ?>
                </label>
                <label class="ch-field">
                    <span class="ch-field__label"><?php esc_html_e('Renovación', 'cheppi-sellers'); ?></span>
                    <select class="ch-input" name="rules[brand_page][renewal_period]">
                        <?php foreach ($periods as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($value, (string) ($brand['renewal_period'] ?? 'none')); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php $fieldError('brand_page.renewal_period'); ?>
                </label>
                <label class="ch-field">
                    <span class="ch-field__label"><?php esc_html_e('Precio de renovación (S/)', 'cheppi-sellers'); ?></span>
                    <input class="ch-input" type="text" inputmode="decimal" name="rules[brand_page][renewal_fee]" value="<?php echo esc_attr((string) ($brand['renewal_fee'] ?? '0.00')); ?>">
                    <?php $fieldError('brand_page.renewal_fee'); ?>
                </label>
                <label class="ch-field">
                    <span class="ch-field__label"><?php esc_html_e('Rondas de cambios', 'cheppi-sellers'); ?></span>
                    <input class="ch-input" type="number" min="0" max="20" name="rules[brand_page][revision_rounds]" value="<?php echo esc_attr((string) ($brand['revision_rounds'] ?? '')); ?>">
                    <?php $fieldError('brand_page.revision_rounds'); ?>
                </label>
                <label class="ch-field">
                    <span class="ch-field__label"><?php esc_html_e('Plazo de entrega (días)', 'cheppi-sellers'); ?></span>
                    <input class="ch-input" type="number" min="1" max="120" name="rules[brand_page][delivery_days]" value="<?php echo esc_attr((string) ($brand['delivery_days'] ?? '')); ?>">
                    <?php $fieldError('brand_page.delivery_days'); ?>
                </label>
            </div>
        </section>

        <div class="ch-admin__actions">
            <button type="submit" class="ch-btn ch-btn--primary"><?php esc_html_e('Guardar reglas', 'cheppi-sellers'); ?></button>
        </div>
    </form>

    <section class="ch-card">
        <h2 class="ch-card__title"><?php esc_html_e('Historial de cambios', 'cheppi-sellers'); ?></h2>
        <?php if ($history === []) : ?>
            <p class="ch-muted ch-card__lead"><?php esc_html_e('Aún no hay cambios guardados.', 'cheppi-sellers'); ?></p>
        <?php else : ?>
            <ul>
                <?php foreach ($history as $entry) :
                    $user = get_userdata($entry['changed_by']);
                    $when = get_date_from_gmt($entry['changed_at'], get_option('date_format') . ' ' . get_option('time_format'));
                    ?>
                    <li>
                        <time><?php echo esc_html($when); ?></time> —
                        <?php echo esc_html($user ? $user->display_name : __('usuario eliminado', 'cheppi-sellers')); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
