<?php
/**
 * Signed in, but without a store yet.
 *
 * @var WP_User $user
 */

use Cheppi\Sellers\Infrastructure\WordPress\Front\Routes;

defined('ABSPATH') || exit;
?>
<div class="ch-auth">
    <?php echo \Cheppi\Sellers\Infrastructure\WordPress\Front\View::capture('panel/_aside'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
    <main class="ch-auth__main">
        <div class="ch-auth__panel">
            <h1><?php echo esc_html(sprintf(__('Hola, %s', 'cheppi-sellers'), $user->display_name)); ?></h1>
            <p class="ch-muted"><?php esc_html_e('Tu cuenta todavía no tiene una tienda en Cheppi. Crea la tuya en 4 pasos.', 'cheppi-sellers'); ?></p>
            <div class="ch-card" style="display:grid;gap:12px">
                <a class="ch-btn ch-btn--primary" href="<?php echo esc_url(Routes::registerUrl()); ?>"><?php esc_html_e('Crear mi tienda', 'cheppi-sellers'); ?></a>
                <a class="ch-btn ch-btn--ghost" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Volver a la tienda', 'cheppi-sellers'); ?></a>
            </div>
        </div>
    </main>
</div>
