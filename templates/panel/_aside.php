<?php
/**
 * Brand column used by guest and registration screens.
 */

use Cheppi\Sellers\Infrastructure\WordPress\Front\Icons;

defined('ABSPATH') || exit;
?>
<aside class="ch-auth__aside">
    <a class="ch-brand" href="<?php echo esc_url(home_url('/')); ?>">
        <span class="ch-brand__word">cheppi</span><span class="ch-brand__tag" style="color:#16112E">MARKET</span>
    </a>
    <p class="ch-auth__headline"><?php esc_html_e('Vende tus productos con comisión baja.', 'cheppi-sellers'); ?></p>
    <ul class="ch-auth__points">
        <li><?php echo Icons::svg('check'); ?><span><?php esc_html_e('Sin costo fijo para empezar.', 'cheppi-sellers'); ?></span></li>
        <li><?php echo Icons::svg('check'); ?><span><?php esc_html_e('Tu propia tienda dentro de Cheppi.', 'cheppi-sellers'); ?></span></li>
        <li><?php echo Icons::svg('check'); ?><span><?php esc_html_e('Pagos en fechas fijas y comisión visible en cada venta.', 'cheppi-sellers'); ?></span></li>
        <li><?php echo Icons::svg('check'); ?><span><?php esc_html_e('Te respondemos en 2 días hábiles.', 'cheppi-sellers'); ?></span></li>
    </ul>
</aside>
