<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress;

/**
 * Folder for vendor documents (DNI, bank proof). Blocked from the web; files are served only
 * through an admin-only, nonce-checked download handler.
 */
final class PrivateUploads
{
    public static function dir(): string
    {
        $uploads = wp_upload_dir(null, false);
        return trailingslashit((string) $uploads['basedir']) . 'cheppi-private';
    }

    public static function protect(): void
    {
        $dir = self::dir();
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        $htaccess = $dir . '/.htaccess';
        if (!is_file($htaccess)) {
            file_put_contents($htaccess, "# Cheppi Sellers: documentos privados\nRequire all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
        }
        if (!is_file($dir . '/index.php')) {
            file_put_contents($dir . '/index.php', "<?php\n// Silence.\n");
        }
    }
}
