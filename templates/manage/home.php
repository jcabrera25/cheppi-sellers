<?php
/**
 * @var list<array{0: string, 1: string, 2: string}> $cards label, value, url
 */

defined('ABSPATH') || exit;
?>
<section class="ch-grid ch-grid--stats ch-manage__todo">
    <?php foreach ($cards as $i => [$label, $value, $url]) : ?>
        <a class="ch-card ch-stat ch-stat--link<?php echo $i === 0 ? ' ch-stat--accent' : ''; ?>" href="<?php echo esc_url($url); ?>">
            <span class="ch-stat__label"><?php echo esc_html($label); ?></span>
            <span class="ch-stat__value"><?php echo esc_html($value); ?></span>
            <span class="ch-small"><?php esc_html_e('Ver', 'cheppi-sellers'); ?> →</span>
        </a>
    <?php endforeach; ?>
</section>
