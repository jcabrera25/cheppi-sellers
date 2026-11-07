<?php
/**
 * Public store page of a vendor.
 *
 * @var \Cheppi\Sellers\Domain\Vendor\Vendor $vendor
 * @var list<WC_Product> $products
 * @var int $total
 * @var int $page
 * @var int $pages
 */

use Cheppi\Sellers\Domain\Shipping\LimaDistricts;
use Cheppi\Sellers\Domain\Vendor\StoreCategory;
use Cheppi\Sellers\Infrastructure\WordPress\Front\Routes;

defined('ABSPATH') || exit;
?>
<div class="ch-store">
    <header class="ch-store__head">
        <div class="ch-store__banner">
            <?php if ($vendor->bannerId) {
                echo wp_get_attachment_image($vendor->bannerId, 'full', false, ['alt' => '', 'fetchpriority' => 'high', 'decoding' => 'async', 'sizes' => '(max-width: 1200px) 100vw, 1200px']);
            } ?>
        </div>
        <div class="ch-store__id">
            <div class="ch-store__logo">
                <?php echo $vendor->logoId
                    ? wp_get_attachment_image($vendor->logoId, 'thumbnail', false, ['alt' => esc_attr($vendor->storeName)])
                    : esc_html(mb_strtoupper(mb_substr($vendor->storeName, 0, 1))); ?>
            </div>
            <div class="ch-store__titles">
                <h1><?php echo esc_html($vendor->storeName); ?></h1>
                <p>
                    <span><?php echo esc_html(StoreCategory::label($vendor->category)); ?></span>
                    <span><?php echo esc_html(sprintf(__('Despacha desde %s', 'cheppi-sellers'), LimaDistricts::label($vendor->district))); ?></span>
                    <?php if ($vendor->instagram !== '') : ?>
                        <a href="<?php echo esc_url('https://instagram.com/' . $vendor->instagram); ?>" target="_blank" rel="noopener nofollow">@<?php echo esc_html($vendor->instagram); ?></a>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <?php if ($vendor->about !== '') : ?>
            <p class="ch-store__about"><?php echo nl2br(esc_html($vendor->about)); ?></p>
        <?php endif; ?>
    </header>

    <section aria-label="<?php esc_attr_e('Productos', 'cheppi-sellers'); ?>">
        <h2 class="ch-store__count"><?php echo esc_html(sprintf(_n('%d producto', '%d productos', $total, 'cheppi-sellers'), $total)); ?></h2>
        <?php if ($products === []) : ?>
            <p class="ch-store__empty"><?php esc_html_e('Esta tienda está preparando sus productos. Vuelve pronto.', 'cheppi-sellers'); ?></p>
        <?php else : ?>
            <ul class="ch-store__grid">
                <?php foreach ($products as $i => $product) : ?>
                    <li class="ch-store__item">
                        <a href="<?php echo esc_url($product->get_permalink()); ?>">
                            <span class="ch-store__img">
                                <?php echo $product->get_image('woocommerce_thumbnail', [
                                    'loading' => $i < 4 ? 'eager' : 'lazy',
                                    'decoding' => 'async',
                                    'sizes' => '(max-width: 600px) 50vw, 280px',
                                ]); ?>
                                <?php if ($product->is_on_sale()) : ?><span class="ch-store__badge"><?php esc_html_e('Oferta', 'cheppi-sellers'); ?></span><?php endif; ?>
                            </span>
                            <span class="ch-store__name"><?php echo esc_html($product->get_name()); ?></span>
                            <span class="ch-store__price"><?php echo wp_kses_post($product->get_price_html()); ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($pages > 1) : ?>
                <nav class="ch-store__pager" aria-label="<?php esc_attr_e('Páginas', 'cheppi-sellers'); ?>">
                    <?php for ($p = 1; $p <= $pages; $p++) : ?>
                        <a href="<?php echo esc_url($p === 1 ? Routes::storeUrl($vendor->storeSlug) : add_query_arg('pagina', $p, Routes::storeUrl($vendor->storeSlug))); ?>" <?php echo $p === $page ? 'aria-current="page"' : ''; ?>><?php echo (int) $p; ?></a>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>
