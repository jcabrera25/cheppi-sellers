<?php
/**
 * Promotions: campaigns, coupons and featured products.
 *
 * @var \Cheppi\Sellers\Domain\Vendor\Vendor $vendor
 * @var list<\Cheppi\Sellers\Domain\Promotion\Campaign> $campaigns open campaigns
 * @var list<string> $joined
 * @var int $normalBps
 * @var list<WC_Coupon> $coupons
 * @var list<array{product: WC_Product, until: int}> $featured
 * @var list<WC_Product> $publishable
 * @var \Cheppi\Sellers\Domain\Money\Money $featuredPrice
 * @var array<string, mixed> $flash
 */

use Cheppi\Sellers\Domain\Promotion\CouponDraft;
use Cheppi\Sellers\Domain\Promotion\FeaturedSpot;
use Cheppi\Sellers\Domain\Rules\Percent;
use Cheppi\Sellers\Infrastructure\WordPress\Front\PanelTools;

defined('ABSPATH') || exit;

$errors = is_array($flash['errors'] ?? null) ? $flash['errors'] : [];
$values = is_array($flash['values'] ?? null) ? $flash['values'] : [];
$now = new DateTimeImmutable('now', wp_timezone());
$post = esc_url(admin_url('admin-post.php'));
?>
<div class="ch-grid ch-grid--2">
    <div class="ch-grid">
        <section class="ch-card">
            <h2 class="ch-card__title"><?php esc_html_e('Campañas Cheppi', 'cheppi-sellers'); ?></h2>
            <p class="ch-muted"><?php echo esc_html(sprintf(__('Únete y paga menos comisión mientras dure la campaña (tu comisión normal: %s%%). Cheppi la promociona en la web y redes.', 'cheppi-sellers'), Percent::fromBps($normalBps))); ?></p>
            <?php if ($campaigns === []) : ?>
                <p class="ch-muted"><?php esc_html_e('No hay campañas abiertas por ahora.', 'cheppi-sellers'); ?></p>
            <?php else : ?>
                <ul class="ch-orders">
                    <?php foreach ($campaigns as $c) :
                        $in = in_array($c->id, $joined, true);
                        ?>
                        <li class="ch-order ch-order--static ch-campaign">
                            <span class="ch-order__main">
                                <span class="ch-order__who"><?php echo esc_html($c->name); ?> <?php if ($c->isActive($now)) : ?><span class="ch-chip ch-chip--success"><?php esc_html_e('En curso', 'cheppi-sellers'); ?></span><?php endif; ?></span>
                                <span class="ch-order__meta"><?php echo esc_html(sprintf(__('Del %1$s al %2$s · comisión %3$s%%', 'cheppi-sellers'), wp_date('j M', $c->startsOn->getTimestamp()), wp_date('j M', $c->endsOn->getTimestamp()), Percent::fromBps($c->commissionBps))); ?><?php echo $c->description !== '' ? ' · ' . esc_html($c->description) : ''; ?></span>
                            </span>
                            <form method="post" action="<?php echo $post; ?>">
                                <input type="hidden" name="action" value="<?php echo esc_attr(PanelTools::CAMPAIGN_TOGGLE); ?>">
                                <input type="hidden" name="campaign" value="<?php echo esc_attr($c->id); ?>">
                                <?php wp_nonce_field(PanelTools::CAMPAIGN_TOGGLE); ?>
                                <button class="ch-btn ch-btn--sm <?php echo $in ? 'ch-btn--ghost' : 'ch-btn--primary'; ?>" type="submit"><?php echo $in ? esc_html__('Salir', 'cheppi-sellers') : esc_html__('Unirme', 'cheppi-sellers'); ?></button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="ch-card">
            <h2 class="ch-card__title"><?php esc_html_e('Tus cupones', 'cheppi-sellers'); ?></h2>
            <p class="ch-muted"><?php esc_html_e('Solo descuentan tus productos. El descuento sale de tu venta y la comisión se calcula sobre el precio final.', 'cheppi-sellers'); ?></p>
            <?php if ($coupons !== []) : ?>
                <ul class="ch-orders" style="margin-bottom:16px">
                    <?php foreach ($coupons as $coupon) :
                        $expires = $coupon->get_date_expires();
                        ?>
                        <li class="ch-order ch-order--static ch-campaign">
                            <span class="ch-order__main">
                                <span class="ch-order__who"><code><?php echo esc_html(strtoupper($coupon->get_code())); ?></code> · <?php echo esc_html($coupon->get_discount_type() === 'percent' ? $coupon->get_amount() . '%' : 'S/ ' . $coupon->get_amount() . ' ' . __('por producto', 'cheppi-sellers')); ?></span>
                                <span class="ch-order__meta"><?php echo esc_html(sprintf(__('Usado %1$d veces%2$s%3$s', 'cheppi-sellers'), $coupon->get_usage_count(), $coupon->get_usage_limit() ? ' de ' . $coupon->get_usage_limit() : '', $expires ? ' · ' . sprintf(__('vence el %s', 'cheppi-sellers'), wp_date('d/m/Y', $expires->getTimestamp())) : '')); ?></span>
                            </span>
                            <form method="post" action="<?php echo $post; ?>" data-confirm="<?php esc_attr_e('¿Desactivar este cupón?', 'cheppi-sellers'); ?>">
                                <input type="hidden" name="action" value="<?php echo esc_attr(PanelTools::COUPON_DELETE); ?>">
                                <input type="hidden" name="coupon_id" value="<?php echo (int) $coupon->get_id(); ?>">
                                <?php wp_nonce_field(PanelTools::COUPON_DELETE); ?>
                                <button class="ch-btn ch-btn--ghost ch-btn--sm" type="submit"><?php esc_html_e('Desactivar', 'cheppi-sellers'); ?></button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <form class="ch-stack" method="post" action="<?php echo $post; ?>">
                <input type="hidden" name="action" value="<?php echo esc_attr(PanelTools::COUPON_CREATE); ?>">
                <?php wp_nonce_field(PanelTools::COUPON_CREATE); ?>
                <div class="ch-row">
                    <label class="ch-field"><span class="ch-field__label"><?php esc_html_e('Código', 'cheppi-sellers'); ?></span>
                        <input class="ch-input" name="code" maxlength="20" required value="<?php echo esc_attr((string) ($values['code'] ?? '')); ?>" placeholder="BIENVENIDA10" style="text-transform:uppercase">
                        <span class="ch-field__error"><?php echo esc_html($errors['code'] ?? ''); ?></span></label>
                    <label class="ch-field"><span class="ch-field__label"><?php esc_html_e('Tipo', 'cheppi-sellers'); ?></span>
                        <select class="ch-input" name="type"><?php foreach (CouponDraft::TYPES as $k => $label) : ?><option value="<?php echo esc_attr($k); ?>" <?php selected((string) ($values['type'] ?? 'percent'), $k); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select>
                        <span class="ch-field__error"><?php echo esc_html($errors['type'] ?? ''); ?></span></label>
                </div>
                <div class="ch-row ch-row--4">
                    <label class="ch-field"><span class="ch-field__label"><?php esc_html_e('Descuento', 'cheppi-sellers'); ?></span>
                        <input class="ch-input" name="amount" inputmode="decimal" required value="<?php echo esc_attr((string) ($values['amount'] ?? '')); ?>" placeholder="10">
                        <span class="ch-field__error"><?php echo esc_html($errors['amount'] ?? ''); ?></span></label>
                    <label class="ch-field"><span class="ch-field__label"><?php esc_html_e('Compra mínima S/', 'cheppi-sellers'); ?></span>
                        <input class="ch-input" name="minimum" inputmode="decimal" value="<?php echo esc_attr((string) ($values['minimum'] ?? '')); ?>" placeholder="<?php esc_attr_e('Opcional', 'cheppi-sellers'); ?>">
                        <span class="ch-field__error"><?php echo esc_html($errors['minimum'] ?? ''); ?></span></label>
                    <label class="ch-field"><span class="ch-field__label"><?php esc_html_e('Vence', 'cheppi-sellers'); ?></span>
                        <input class="ch-input" type="date" name="expires" value="<?php echo esc_attr((string) ($values['expires'] ?? '')); ?>">
                        <span class="ch-field__error"><?php echo esc_html($errors['expires'] ?? ''); ?></span></label>
                    <label class="ch-field"><span class="ch-field__label"><?php esc_html_e('Usos máx.', 'cheppi-sellers'); ?></span>
                        <input class="ch-input" name="limit" inputmode="numeric" value="<?php echo esc_attr((string) ($values['limit'] ?? '')); ?>" placeholder="<?php esc_attr_e('Sin límite', 'cheppi-sellers'); ?>">
                        <span class="ch-field__error"><?php echo esc_html($errors['limit'] ?? ''); ?></span></label>
                </div>
                <div class="ch-actions"><button class="ch-btn ch-btn--primary" type="submit"><?php esc_html_e('Crear cupón', 'cheppi-sellers'); ?></button></div>
            </form>
        </section>
    </div>

    <div class="ch-grid ch-sticky">
        <section class="ch-card ch-stack">
            <h2 class="ch-card__title"><?php esc_html_e('Destacar un producto', 'cheppi-sellers'); ?></h2>
            <p class="ch-muted"><?php echo esc_html(sprintf(__('Aparece en las secciones de destacados de Cheppi. S/ %s por día; se descuenta de tu próxima liquidación.', 'cheppi-sellers'), $featuredPrice->toDecimal())); ?></p>
            <?php if ($publishable === []) : ?>
                <p class="ch-muted"><?php esc_html_e('Necesitas al menos un producto publicado.', 'cheppi-sellers'); ?></p>
            <?php else : ?>
                <form class="ch-stack" method="post" action="<?php echo $post; ?>" data-confirm="<?php esc_attr_e('¿Confirmas la compra del destacado? El monto se descuenta de tu próxima liquidación.', 'cheppi-sellers'); ?>">
                    <input type="hidden" name="action" value="<?php echo esc_attr(PanelTools::FEATURE_BUY); ?>">
                    <?php wp_nonce_field(PanelTools::FEATURE_BUY); ?>
                    <label class="ch-field"><span class="ch-field__label"><?php esc_html_e('Producto', 'cheppi-sellers'); ?></span>
                        <select class="ch-input" name="product_id"><?php foreach ($publishable as $p) : ?><option value="<?php echo (int) $p->get_id(); ?>"><?php echo esc_html($p->get_name()); ?></option><?php endforeach; ?></select></label>
                    <label class="ch-field"><span class="ch-field__label"><?php esc_html_e('Días', 'cheppi-sellers'); ?></span>
                        <select class="ch-input" name="days"><?php foreach ([3, 7, 15, 30] as $d) : ?><option value="<?php echo (int) $d; ?>"><?php echo esc_html(sprintf(__('%1$d días · S/ %2$s', 'cheppi-sellers'), $d, number_format($featuredPrice->cents * $d / 100, 2))); ?></option><?php endforeach; ?></select></label>
                    <button class="ch-btn ch-btn--primary" type="submit"><?php esc_html_e('Destacar', 'cheppi-sellers'); ?></button>
                </form>
            <?php endif; ?>
            <?php if ($featured !== []) : ?>
                <h3 class="ch-card__title" style="font-size:1rem"><?php esc_html_e('Destacados activos', 'cheppi-sellers'); ?></h3>
                <ul class="ch-tasks">
                    <?php foreach ($featured as $f) : ?>
                        <li class="ch-small"><strong><?php echo esc_html($f['product']->get_name()); ?></strong> — <?php echo esc_html(sprintf(__('hasta el %s', 'cheppi-sellers'), wp_date('d/m/Y', $f['until']))); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <p class="ch-muted ch-small"><?php echo esc_html(sprintf(__('Máximo %d días por compra; si compras de nuevo, se suma al periodo actual.', 'cheppi-sellers'), FeaturedSpot::MAX_DAYS)); ?></p>
        </section>
    </div>
</div>
