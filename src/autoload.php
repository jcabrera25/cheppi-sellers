<?php
/**
 * PSR-4 autoloader for Cheppi\Sellers\. Lets the plugin run on hosting without Composer.
 */
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'Cheppi\\Sellers\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    if (str_starts_with($relative, 'Tests\\')) {
        $file = dirname(__DIR__) . '/tests/' . str_replace('\\', '/', substr($relative, 6)) . '.php';
    } else {
        $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    }
    if (is_file($file)) {
        require $file;
    }
});
