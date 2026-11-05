<?php
/**
 * "Mi tienda": logo, banner and presentation shown on the public store page.
 *
 * @var \Cheppi\Sellers\Domain\Vendor\Vendor $vendor
 * @var array<string, mixed> $flash
 */

use Cheppi\Sellers\Infrastructure\WordPress\Front\Icons;
use Cheppi\Sellers\Infrastructure\WordPress\Front\PanelHandler;
use Cheppi\Sellers\Infrastructure\WordPress\Front\Routes;

defined('ABSPATH') || exit;

$errors = is_array($flash['errors'] ?? null) ? $flash['errors'] : [];
?>
<form class="ch-grid ch-grid--2" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <input type="hidden" name="action" value="<?php echo esc_attr(PanelHandler::SAVE_STORE); ?>">
    <?php wp_nonce_field(PanelHandler::SAVE_STORE); ?>

    <div class="ch-grid">
        <section class="ch-card ch-stack">
            <h2 class="ch-card__title"><?php esc_html_e('Imagen de tu tienda', 'cheppi-sellers'); ?></h2>
            <div class="ch-store-preview">
                <div class="ch-store-preview__banner">
                    <?php echo $vendor->bannerId ? wp_get_attachment_image($vendor->bannerId, 'large', false, ['alt' => '']) : ''; ?>
                </div>
                <div class="ch-store-preview__logo">
                    <?php echo $vendor->logoId ? wp_get_attachment_image($vendor->logoId, 'thumbnail', false, ['alt' => '']) : esc_html(mb_strtoupper(mb_substr($vendor->storeName, 0, 1))); ?>
                </div>
            </div>
            <div class="ch-row">
                <div class="ch-field">
                    <label class="ch-file" data-file>
                        <input type="file" name="logo" accept="image/jpeg,image/png,image/webp">
                        <span class="ch-file__icon"><?php echo Icons::svg('upload'); ?></span>
                        <span class="ch-file__text"><span class="ch-file__name" data-file-name><?php esc_html_e('Logo', 'cheppi-sellers'); ?></span><span class="ch-field__help" data-file-help><?php esc_html_e('Cuadrado, mínimo 400 px', 'cheppi-sellers'); ?></span></span>
                    </label>
                    <?php if ($vendor->logoId) : ?><label class="ch-check ch-small"><input type="checkbox" name="remove_logo" value="1"> <span><?php esc_html_e('Quitar logo', 'cheppi-sellers'); ?></span></label><?php endif; ?>
                    <span class="ch-field__error"><?php echo esc_html($errors['logo'] ?? ''); ?></span>
                </div>
                <div class="ch-field">
                    <label class="ch-file" data-file>
                        <input type="file" name="banner" accept="image/jpeg,image/png,image/webp">
                        <span class="ch-file__icon"><?php echo Icons::svg('upload'); ?></span>
                        <span class="ch-file__text"><span class="ch-file__name" data-file-name><?php esc_html_e('Portada', 'cheppi-sellers'); ?></span><span class="ch-field__help" data-file-help><?php esc_html_e('Horizontal, 1600 × 500 px', 'cheppi-sellers'); ?></span></span>
                    </label>
                    <?php if ($vendor->bannerId) : ?><label class="ch-check ch-small"><input type="checkbox" name="remove_banner" value="1"> <span><?php esc_html_e('Quitar portada', 'cheppi-sellers'); ?></span></label><?php endif; ?>
                    <span class="ch-field__error"><?php echo esc_html($errors['banner'] ?? ''); ?></span>
                </div>
            </div>
        </section>

        <section class="ch-card ch-stack">
            <label class="ch-field">
                <span class="ch-field__label"><?php esc_html_e('Presentación', 'cheppi-sellers'); ?> <small><?php esc_html_e('hasta 1000 caracteres', 'cheppi-sellers'); ?></small></span>
                <textarea class="ch-input" name="about" rows="5" maxlength="1000" placeholder="<?php esc_attr_e('Cuenta quiénes son, qué venden y por qué elegirlos.', 'cheppi-sellers'); ?>"><?php echo esc_textarea($vendor->about); ?></textarea>
                <span class="ch-field__error"><?php echo esc_html($errors['about'] ?? ''); ?></span>
            </label>
        </section>
    </div>

    <div class="ch-grid ch-sticky">
        <section class="ch-card ch-stack">
            <h2 class="ch-card__title"><?php esc_html_e('Tu dirección', 'cheppi-sellers'); ?></h2>
            <p class="ch-muted ch-small ch-break"><?php echo esc_html(Routes::storeUrl($vendor->storeSlug)); ?></p>
            <a class="ch-btn ch-btn--ghost" href="<?php echo esc_url(Routes::storeUrl($vendor->storeSlug)); ?>" target="_blank" rel="noopener"><?php esc_html_e('Ver mi tienda', 'cheppi-sellers'); ?></a>
            <button type="submit" class="ch-btn ch-btn--primary"><?php esc_html_e('Guardar', 'cheppi-sellers'); ?></button>
        </section>
    </div>
</form>
