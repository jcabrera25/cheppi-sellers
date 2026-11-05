<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Front;

/**
 * One-time message (and form values/errors) carried across a redirect for the signed-in user.
 */
final class Flash
{
    /** @param array<string, mixed> $data */
    public static function set(array $data): void
    {
        set_transient(self::key(), $data, 300);
    }

    /** @return array<string, mixed> */
    public static function take(): array
    {
        $key = self::key();
        $data = get_transient($key);
        if ($data === false) {
            return [];
        }
        delete_transient($key);
        return is_array($data) ? $data : [];
    }

    private static function key(): string
    {
        return 'cheppi_flash_' . get_current_user_id();
    }
}
