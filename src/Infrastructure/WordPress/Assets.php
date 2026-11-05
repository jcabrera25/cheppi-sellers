<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress;

/**
 * Loads compiled assets from assets/dist as native ES modules (deferred by the browser).
 * Each screen enqueues only its own entry; nothing loads globally.
 */
final class Assets
{
    public function enqueue(string $entry): void
    {
        $base = CHEPPI_SELLERS_DIR . '/assets/dist/';
        $url = plugins_url('assets/dist/', CHEPPI_SELLERS_FILE);
        $handle = 'cheppi-' . $entry;

        if (is_file($base . $entry . '.css')) {
            wp_enqueue_style($handle, $url . $entry . '.css', [], $this->version($base . $entry . '.css'));
        }
        if (is_file($base . $entry . '.js')) {
            wp_enqueue_script_module($handle, $url . $entry . '.js', [], $this->version($base . $entry . '.js'));
        }
    }

    private function version(string $file): string
    {
        return CHEPPI_SELLERS_VERSION . '.' . (string) filemtime($file);
    }
}
