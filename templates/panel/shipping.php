<?php
/**
 * Delivery zones: which districts the vendor serves, at what price and in how many days.
 *
 * @var list<\Cheppi\Sellers\Domain\Shipping\DeliveryZone> $zones
 * @var array<string, mixed> $flash
 */

use Cheppi\Sellers\Domain\Shipping\DeliveryZone;
use Cheppi\Sellers\Domain\Shipping\LimaDistricts;
use Cheppi\Sellers\Infrastructure\WordPress\Front\OrdersHandler;

defined('ABSPATH') || exit;

$errors = is_array($flash['errors'] ?? null) ? $flash['errors'] : [];
if (is_array($flash['values'] ?? null)) {
    $rows = $flash['values'];
} else {
    $rows = array_map(static fn (DeliveryZone $z): array => [
        'name' => $z->name,
        'districts' => $z->districtCodes,
        'cost' => $z->cost->toDecimal(),
        'eta_days' => (string) $z->etaDays,
        'free_from' => $z->freeFrom?->toDecimal() ?? '',
    ], $zones);
}
if ($rows === []) {
    $rows[] = ['name' => __('Lima cerca', 'cheppi-sellers'), 'districts' => [], 'cost' => '', 'eta_days' => '1', 'free_from' => ''];
}
$rows[] = ['name' => '', 'districts' => [], 'cost' => '', 'eta_days' => '', 'free_from' => '']; // one empty zone to add
$rows = array_slice($rows, 0, DeliveryZone::MAX_ZONES);
$err = static fn (int $i, string $f): string => (string) ($errors["zones.$i.$f"] ?? '');
?>
<div class="ch-grid ch-grid--2">
    <form class="ch-grid" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="<?php echo esc_attr(OrdersHandler::SAVE_ZONES); ?>">
        <?php wp_nonce_field(OrdersHandler::SAVE_ZONES); ?>
        <?php foreach ($rows as $i => $row) :
            $selected = array_map('strval', (array) ($row['districts'] ?? []));
            $isEmpty = $selected === [] && ($row['name'] ?? '') === '';
            ?>
            <details class="ch-card ch-zone" <?php echo $isEmpty && $err($i, 'name') === '' ? '' : 'open'; ?>>
                <summary class="ch-zone__head">
                    <span class="ch-card__title"><?php echo $isEmpty ? esc_html__('+ Agregar zona', 'cheppi-sellers') : esc_html($row['name'] ?: __('Zona', 'cheppi-sellers')); ?></span>
                    <span class="ch-muted ch-small"><?php echo in_array(DeliveryZone::ANYWHERE, $selected, true) ? esc_html__('Todos los demás distritos', 'cheppi-sellers') : ($selected !== [] ? esc_html(sprintf(_n('%d distrito', '%d distritos', count($selected), 'cheppi-sellers'), count($selected))) : ''); ?><?php echo ($row['cost'] ?? '') !== '' ? ' · S/ ' . esc_html((string) $row['cost']) : ''; ?></span>
                </summary>
                <div class="ch-stack">
                    <div class="ch-row ch-row--4">
                        <label class="ch-field">
                            <span class="ch-field__label"><?php esc_html_e('Nombre', 'cheppi-sellers'); ?></span>
                            <input class="ch-input" name="zones[<?php echo (int) $i; ?>][name]" maxlength="40" value="<?php echo esc_attr((string) ($row['name'] ?? '')); ?>" placeholder="<?php esc_attr_e('Lima cerca', 'cheppi-sellers'); ?>">
                            <span class="ch-field__error"><?php echo esc_html($err($i, 'name')); ?></span>
                        </label>
                        <label class="ch-field">
                            <span class="ch-field__label"><?php esc_html_e('Tarifa S/', 'cheppi-sellers'); ?></span>
                            <input class="ch-input" name="zones[<?php echo (int) $i; ?>][cost]" inputmode="decimal" value="<?php echo esc_attr((string) ($row['cost'] ?? '')); ?>" placeholder="10.00">
                            <span class="ch-field__error"><?php echo esc_html($err($i, 'cost')); ?></span>
                        </label>
                        <label class="ch-field">
                            <span class="ch-field__label"><?php esc_html_e('Días de entrega', 'cheppi-sellers'); ?></span>
                            <input class="ch-input" name="zones[<?php echo (int) $i; ?>][eta_days]" inputmode="numeric" value="<?php echo esc_attr((string) ($row['eta_days'] ?? '')); ?>" placeholder="1">
                            <span class="ch-field__error"><?php echo esc_html($err($i, 'eta_days')); ?></span>
                        </label>
                        <label class="ch-field">
                            <span class="ch-field__label"><?php esc_html_e('Gratis desde S/', 'cheppi-sellers'); ?></span>
                            <input class="ch-input" name="zones[<?php echo (int) $i; ?>][free_from]" inputmode="decimal" value="<?php echo esc_attr((string) ($row['free_from'] ?? '')); ?>" placeholder="<?php esc_attr_e('Opcional', 'cheppi-sellers'); ?>">
                            <span class="ch-field__error"><?php echo esc_html($err($i, 'free_from')); ?></span>
                        </label>
                    </div>
                    <fieldset class="ch-districts">
                        <legend class="ch-field__label"><?php esc_html_e('Distritos', 'cheppi-sellers'); ?></legend>
                        <label class="ch-check ch-districts__all"><input type="checkbox" name="zones[<?php echo (int) $i; ?>][districts][]" value="*" <?php checked(in_array('*', $selected, true)); ?>> <span><?php esc_html_e('Todos los demás distritos de Lima y Callao', 'cheppi-sellers'); ?></span></label>
                        <div class="ch-districts__grid">
                            <?php foreach (LimaDistricts::ALL as $code => $label) : ?>
                                <label class="ch-check"><input type="checkbox" name="zones[<?php echo (int) $i; ?>][districts][]" value="<?php echo esc_attr($code); ?>" <?php checked(in_array($code, $selected, true)); ?>> <span><?php echo esc_html($label); ?></span></label>
                            <?php endforeach; ?>
                        </div>
                        <span class="ch-field__error"><?php echo esc_html($err($i, 'districts')); ?></span>
                    </fieldset>
                    <?php if (!$isEmpty) : ?><p class="ch-muted ch-small"><?php esc_html_e('Para quitar la zona, desmarca todos sus distritos y guarda.', 'cheppi-sellers'); ?></p><?php endif; ?>
                </div>
            </details>
        <?php endforeach; ?>
        <div class="ch-actions"><button type="submit" class="ch-btn ch-btn--primary"><?php esc_html_e('Guardar zonas', 'cheppi-sellers'); ?></button></div>
    </form>

    <div class="ch-grid ch-sticky">
        <section class="ch-card ch-stack">
            <h2 class="ch-card__title"><?php esc_html_e('Cómo funciona', 'cheppi-sellers'); ?></h2>
            <p class="ch-muted"><?php esc_html_e('Tú entregas tus pedidos. En el carrito, el cliente ve la tarifa de la zona que incluye su distrito y la paga junto con su compra; ese monto es tuyo y no paga comisión.', 'cheppi-sellers'); ?></p>
            <p class="ch-muted"><?php esc_html_e('Si un distrito no está en ninguna zona, ese cliente no podrá comprarte. Marca «Todos los demás distritos» en una zona para cubrir el resto.', 'cheppi-sellers'); ?></p>
            <p class="ch-muted"><?php esc_html_e('Mientras no tengas zonas, se usa la tarifa general de Cheppi.', 'cheppi-sellers'); ?></p>
        </section>
    </div>
</div>
