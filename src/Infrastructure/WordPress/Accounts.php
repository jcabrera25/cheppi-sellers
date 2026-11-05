<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress;

use WP_Error;

/**
 * Creates WordPress accounts for vendors (customer role in WooCommerce + the vendor role added on approval/creation).
 */
final class Accounts
{
    /**
     * @param string|null $password null = generate one and email a "set your password" link when $sendWelcome is true
     */
    public static function create(string $name, string $email, ?string $password, bool $sendWelcome = false): int|WP_Error
    {
        $base = sanitize_user(current(explode('@', $email)), true) ?: 'vendedor';
        $login = $base;
        for ($i = 2; username_exists($login); $i++) {
            $login = $base . $i;
        }
        $parts = preg_split('/\s+/', trim($name), 2) ?: [];
        $role = get_role('customer') !== null ? 'customer' : (string) get_option('default_role', 'subscriber');

        $userId = wp_insert_user([
            'user_login' => $login,
            'user_email' => $email,
            'user_pass' => $password ?? wp_generate_password(24, true, true),
            'display_name' => $name,
            'first_name' => $parts[0] ?? '',
            'last_name' => $parts[1] ?? '',
            'role' => $role,
        ]);

        if (!$userId instanceof WP_Error && $sendWelcome) {
            wp_new_user_notification($userId, null, 'user');
        }
        return $userId;
    }
}
