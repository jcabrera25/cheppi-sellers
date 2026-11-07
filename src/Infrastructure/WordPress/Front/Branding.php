<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Front;

/**
 * The site's own logo for plugin screens: the theme's custom logo, or the media file named "cheppi-logo…".
 * The lookup result is cached in an autoloaded option so it costs no query per request.
 */
final class Branding
{
    private const OPTION = 'cheppi_sellers_logo_id';

    public static function logoId(): int
    {
        $id = (int) get_theme_mod('custom_logo');
        if ($id > 0) {
            return $id;
        }
        $cached = get_option(self::OPTION, null);
        if ($cached !== null && $cached !== false) {
            return (int) $cached;
        }
        $found = get_posts([
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'name' => 'cheppi-logo-color',
            'numberposts' => 1,
            'fields' => 'ids',
        ]);
        $id = (int) ($found[0] ?? 0);
        update_option(self::OPTION, (string) $id, true);
        return $id;
    }

    /** Linked logo image (or the text mark when the site has none). */
    public static function logo(string $class = 'ch-brand'): string
    {
        $id = (int) apply_filters('cheppi_sellers_logo_id', self::logoId());
        $inner = $id > 0
            ? wp_get_attachment_image($id, 'medium', false, [
                'alt' => get_bloginfo('name'),
                'class' => 'ch-brand__img',
                'sizes' => '160px',
                'decoding' => 'async',
                'loading' => 'eager',
            ])
            : '<span class="ch-brand__word">cheppi</span><span class="ch-brand__tag">MARKET</span>';
        return sprintf('<a class="%s" href="%s">%s</a>', esc_attr($class), esc_url(home_url('/')), $inner);
    }
}
