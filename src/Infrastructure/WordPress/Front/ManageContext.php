<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Front;

/**
 * Cheppi's management screens exist twice: in the front-end panel (/gestion/, the main one) and, as a
 * fallback, in wp-admin. This decides which URL a management link should use: wp-admin only while the
 * admin is working inside wp-admin; everywhere else (front panel, its forms, e-mails, cron) the panel.
 */
final class ManageContext
{
    /** wp-admin page slug => panel section */
    private const SECTIONS = [
        'cheppi-metrics' => '',
        'cheppi-vendors' => 'tiendas',
        'cheppi-payouts' => 'liquidaciones',
        'cheppi-campaigns' => 'campanas',
        'cheppi-rules' => 'reglas',
    ];

    public static function useFront(): bool
    {
        if (wp_doing_cron()) {
            return true;
        }
        $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($script === 'admin-post.php') {
            return !str_contains((string) wp_get_referer(), '/wp-admin/');
        }
        return !is_admin();
    }

    /** URL of a management page given its wp-admin slug and query args. @param array<string, scalar> $args */
    public static function url(string $page, array $args = []): string
    {
        if (!self::useFront() || !array_key_exists($page, self::SECTIONS)) {
            return add_query_arg($args, admin_url('admin.php?page=' . $page));
        }
        $path = self::SECTIONS[$page];
        if ($page === 'cheppi-vendors') {
            if (!empty($args['vendor'])) {
                $path .= '/' . (int) $args['vendor'];
            } elseif (!empty($args['nuevo'])) {
                $path .= '/nueva';
            }
            unset($args['vendor'], $args['nuevo'], $args['page']);
        }
        return add_query_arg($args, Routes::manageUrl($path));
    }
}
