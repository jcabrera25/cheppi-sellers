<?php
/**
 * Business fields of a vendor, shared by "Añadir vendedor" and "Editar datos".
 *
 * @var array<string, string> $values
 * @var array<string, string> $errors
 * @var bool $showSlug
 */

use Cheppi\Sellers\Domain\Shipping\LimaDistricts;
use Cheppi\Sellers\Domain\Vendor\StoreCategory;

defined('ABSPATH') || exit;

$val = static fn (string $k): string => (string) ($values[$k] ?? '');
$err = static function (string $k) use ($errors): void {
    if (isset($errors[$k])) {
        printf('<span class="ch-field__error">%s</span>', esc_html($errors[$k]));
    }
};
$inv = static fn (string $k): string => isset($errors[$k]) ? ' aria-invalid="true"' : '';
?>
<div class="ch-form-grid">
    <label class="ch-field ch-span-2">
        <span class="ch-field__label"><?php esc_html_e('Nombre de la tienda o marca', 'cheppi-sellers'); ?></span>
        <input class="ch-input" name="store_name" required maxlength="120" value="<?php echo esc_attr($val('store_name')); ?>"<?php echo $inv('store_name'); ?>>
        <?php $err('store_name'); ?>
    </label>
    <?php if (!empty($showSlug)) : ?>
        <label class="ch-field ch-span-2">
            <span class="ch-field__label"><?php esc_html_e('Dirección de la tienda', 'cheppi-sellers'); ?></span>
            <span class="ch-input-prefix"><span><?php echo esc_html(home_url('/tienda/')); ?></span><input class="ch-input" name="store_slug" maxlength="60" value="<?php echo esc_attr($val('store_slug')); ?>"<?php echo $inv('store_slug'); ?>></span>
            <?php $err('store_slug'); ?>
        </label>
    <?php endif; ?>
    <label class="ch-field">
        <span class="ch-field__label">RUC</span>
        <input class="ch-input" name="ruc" inputmode="numeric" required maxlength="13" value="<?php echo esc_attr($val('ruc')); ?>"<?php echo $inv('ruc'); ?>>
        <?php $err('ruc'); ?>
    </label>
    <label class="ch-field">
        <span class="ch-field__label"><?php esc_html_e('Celular', 'cheppi-sellers'); ?></span>
        <input class="ch-input" type="tel" name="phone" required value="<?php echo esc_attr($val('phone')); ?>"<?php echo $inv('phone'); ?>>
        <?php $err('phone'); ?>
    </label>
    <label class="ch-field ch-span-2">
        <span class="ch-field__label"><?php esc_html_e('Razón social o titular del RUC', 'cheppi-sellers'); ?></span>
        <input class="ch-input" name="legal_name" required maxlength="200" value="<?php echo esc_attr($val('legal_name')); ?>"<?php echo $inv('legal_name'); ?>>
        <?php $err('legal_name'); ?>
    </label>
    <label class="ch-field">
        <span class="ch-field__label"><?php esc_html_e('Categoría principal', 'cheppi-sellers'); ?></span>
        <select class="ch-input" name="category" required<?php echo $inv('category'); ?>>
            <option value=""><?php esc_html_e('Elige una', 'cheppi-sellers'); ?></option>
            <?php foreach (StoreCategory::ALL as $code => $label) : ?>
                <option value="<?php echo esc_attr($code); ?>" <?php selected($val('category'), $code); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <?php $err('category'); ?>
    </label>
    <label class="ch-field">
        <span class="ch-field__label"><?php esc_html_e('Distrito de despacho', 'cheppi-sellers'); ?></span>
        <select class="ch-input" name="district" required<?php echo $inv('district'); ?>>
            <option value=""><?php esc_html_e('Elige un distrito', 'cheppi-sellers'); ?></option>
            <?php foreach (LimaDistricts::ALL as $code => $label) : ?>
                <option value="<?php echo esc_attr($code); ?>" <?php selected($val('district'), $code); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <?php $err('district'); ?>
    </label>
    <label class="ch-field">
        <span class="ch-field__label">Instagram <small><?php esc_html_e('opcional', 'cheppi-sellers'); ?></small></span>
        <input class="ch-input" name="instagram" placeholder="@marca" value="<?php echo esc_attr($val('instagram')); ?>"<?php echo $inv('instagram'); ?>>
        <?php $err('instagram'); ?>
    </label>
    <label class="ch-field">
        <span class="ch-field__label"><?php esc_html_e('Web', 'cheppi-sellers'); ?> <small><?php esc_html_e('opcional', 'cheppi-sellers'); ?></small></span>
        <input class="ch-input" type="url" name="website" placeholder="https://" value="<?php echo esc_attr($val('website')); ?>"<?php echo $inv('website'); ?>>
        <?php $err('website'); ?>
    </label>
</div>
