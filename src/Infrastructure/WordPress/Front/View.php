<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Front;

use InvalidArgumentException;

/**
 * Renders a PHP template from /templates with its own variables and returns the HTML.
 */
final class View
{
    /** @param array<string, mixed> $vars */
    public static function capture(string $template, array $vars = []): string
    {
        if (!preg_match('#^[a-z0-9/_-]+$#', $template)) {
            throw new InvalidArgumentException('Invalid template name.');
        }
        $file = CHEPPI_SELLERS_DIR . '/templates/' . $template . '.php';
        if (!is_file($file)) {
            throw new InvalidArgumentException("Template not found: {$template}");
        }
        ob_start();
        (static function (string $__file, array $__vars): void {
            extract($__vars, EXTR_SKIP);
            require $__file;
        })($file, $vars);
        return (string) ob_get_clean();
    }
}
