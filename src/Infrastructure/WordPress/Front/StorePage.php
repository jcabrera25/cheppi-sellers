<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Front;

/**
 * A real WordPress page ("Tiendas", /tienda/) hosts every public store URL.
 * Being a normal page lets the theme (Divi's Theme Builder header, footer and their generated styles) work as on any other page.
 */
final class StorePage
{
    public const OPTION = 'cheppi_sellers_store_page_id';

    public static function id(): int
    {
        return (int) get_option(self::OPTION, 0);
    }

    /** Creates the page once (or restores it from the trash). Safe to call on every upgrade. */
    public static function ensure(): void
    {
        $id = self::id();
        $post = $id > 0 ? get_post($id) : null;
        if ($post !== null && $post->post_type === 'page') {
            if ($post->post_status !== 'publish') {
                wp_update_post(['ID' => $id, 'post_status' => 'publish']);
            }
            return;
        }
        $existing = get_page_by_path(Routes::STORE_BASE);
        $id = $existing !== null ? (int) $existing->ID : (int) wp_insert_post([
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_title' => __('Tiendas', 'cheppi-sellers'),
            'post_name' => Routes::STORE_BASE,
            'post_content' => '',
            'comment_status' => 'closed',
        ]);
        if ($id > 0) {
            update_post_meta($id, '_et_pb_page_layout', 'et_full_width_page');
            update_option(self::OPTION, (string) $id, true);
            update_option('cheppi_sellers_flush_rewrites', '1', true); // store URLs now point to this page

        }
    }
}
