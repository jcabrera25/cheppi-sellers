<?php
/**
 * @var int $productId
 * @var list<\Cheppi\Sellers\Domain\Vendor\Vendor> $vendors approved
 */

use Cheppi\Sellers\Infrastructure\WordPress\Front\Routes;

defined('ABSPATH') || exit;
?>
<section class="ch-card ch-stack" style="max-width:560px">
    <?php if ($productId > 0) : ?>
        <h2 class="ch-card__title"><?php esc_html_e('Este producto es de Cheppi', 'cheppi-sellers'); ?></h2>
        <p class="ch-muted"><?php esc_html_e('No pertenece a ninguna tienda, así que se edita en WooCommerce.', 'cheppi-sellers'); ?></p>
        <div><a class="ch-btn ch-btn--primary" href="<?php echo esc_url(admin_url('post.php?post=' . $productId . '&action=edit')); ?>"><?php esc_html_e('Editar en WooCommerce', 'cheppi-sellers'); ?></a></div>
    <?php else : ?>
        <h2 class="ch-card__title"><?php esc_html_e('¿Para qué tienda es el producto?', 'cheppi-sellers'); ?></h2>
        <form method="get" action="<?php echo esc_url(Routes::manageProductUrl(0)); ?>" class="ch-stack">
            <select class="ch-input" name="tienda" required>
                <option value=""><?php esc_html_e('Elige una tienda aprobada', 'cheppi-sellers'); ?></option>
                <?php foreach ($vendors as $v) : ?><option value="<?php echo (int) $v->id; ?>"><?php echo esc_html($v->storeName); ?></option><?php endforeach; ?>
            </select>
            <div><button class="ch-btn ch-btn--primary" type="submit"><?php esc_html_e('Continuar', 'cheppi-sellers'); ?></button></div>
        </form>
    <?php endif; ?>
</section>
