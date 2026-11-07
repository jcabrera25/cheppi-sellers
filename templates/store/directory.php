<?php
/**
 * Directory of approved stores (/tienda/).
 *
 * @var list<\Cheppi\Sellers\Domain\Vendor\Vendor> $vendors
 */

use Cheppi\Sellers\Domain\Vendor\StoreCategory;
use Cheppi\Sellers\Infrastructure\WordPress\Front\Routes;

defined('ABSPATH') || exit;
?>
<div class="ch-store">
    <h1 class="ch-store__title"><?php esc_html_e('Tiendas en Cheppi', 'cheppi-sellers'); ?></h1>
    <p class="ch-store__about"><?php esc_html_e('Marcas y emprendedores que venden en Cheppi Market.', 'cheppi-sellers'); ?></p>
    <?php if ($vendors === []) : ?>
        <p class="ch-store__empty"><?php esc_html_e('Muy pronto verás aquí las primeras tiendas.', 'cheppi-sellers'); ?></p>
    <?php else : ?>
        <ul class="ch-store__grid ch-store__grid--brands">
            <?php foreach ($vendors as $vendor) : ?>
                <li class="ch-store__item">
                    <a href="<?php echo esc_url(Routes::storeUrl($vendor->storeSlug)); ?>">
                        <span class="ch-store__brand">
                            <?php echo $vendor->logoId
                                ? wp_get_attachment_image($vendor->logoId, 'thumbnail', false, ['alt' => '', 'loading' => 'lazy', 'decoding' => 'async'])
                                : esc_html(mb_strtoupper(mb_substr($vendor->storeName, 0, 1))); ?>
                        </span>
                        <span class="ch-store__name"><?php echo esc_html($vendor->storeName); ?></span>
                        <span class="ch-store__meta"><?php echo esc_html(StoreCategory::label($vendor->category)); ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
