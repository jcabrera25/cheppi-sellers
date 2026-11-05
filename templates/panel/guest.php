<?php
/**
 * Not signed in.
 */

use Cheppi\Sellers\Infrastructure\WordPress\Front\Routes;

defined('ABSPATH') || exit;
?>
<div class="ch-auth">
    <?php echo \Cheppi\Sellers\Infrastructure\WordPress\Front\View::capture('panel/_aside'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
    <main class="ch-auth__main">
        <div class="ch-auth__panel">
            <h1><?php esc_html_e('Tu panel de vendedor', 'cheppi-sellers'); ?></h1>
            <p class="ch-muted"><?php esc_html_e('Ingresa con tu cuenta para ver tu tienda, o crea tu tienda en 4 pasos.', 'cheppi-sellers'); ?></p>
            <div class="ch-card" style="display:grid;gap:12px">
                <a class="ch-btn ch-btn--secondary" href="<?php echo esc_url(wp_login_url(Routes::panelUrl())); ?>"><?php esc_html_e('Ingresar', 'cheppi-sellers'); ?></a>
                <a class="ch-btn ch-btn--primary" href="<?php echo esc_url(Routes::registerUrl()); ?>"><?php esc_html_e('Crear mi tienda', 'cheppi-sellers'); ?></a>
            </div>
        </div>
    </main>
</div>
