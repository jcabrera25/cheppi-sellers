<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress;

use Throwable;

/**
 * Records unexpected errors in the private uploads folder (not reachable from the web) and returns a short
 * code the user can quote, so support can find the exact error without showing internals on screen.
 */
final class ErrorLog
{
    public static function record(Throwable|string $error, string $context): string
    {
        $code = strtoupper(substr(md5(uniqid('', true)), 0, 6));
        $message = $error instanceof Throwable
            ? sprintf('%s: %s in %s:%d', $error::class, $error->getMessage(), $error->getFile(), $error->getLine())
            : $error;
        $line = sprintf("[%s] %s %s user=%d %s\n", gmdate('Y-m-d H:i:s'), $code, $context, get_current_user_id(), $message);
        error_log('Cheppi Sellers ' . trim($line));
        $dir = PrivateUploads::dir() . '/logs';
        if (wp_mkdir_p($dir)) {
            $file = $dir . '/errors.log';
            if (is_file($file) && filesize($file) > 512 * 1024) {
                @rename($file, $file . '.1'); // keep the log small
            }
            @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
        }
        return $code;
    }
}
