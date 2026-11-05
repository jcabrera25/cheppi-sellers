<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Front;

use Cheppi\Sellers\Application\BusinessRulesRepository;
use Cheppi\Sellers\Application\DocumentStore;
use Cheppi\Sellers\Application\VendorRepository;
use Cheppi\Sellers\Domain\Vendor\Vendor;
use Cheppi\Sellers\Infrastructure\WordPress\Assets;
use WP_User;

/**
 * Renders the seller panel as a standalone app page (no theme, no Divi), so it stays fast and consistent.
 */
final class PanelController
{
    public function __construct(
        private readonly VendorRepository $vendors,
        private readonly DocumentStore $documents,
        private readonly BusinessRulesRepository $rules,
        private readonly Assets $assets
    ) {
    }

    public function register(): void
    {
        add_action('template_redirect', [$this, 'route'], 1);
    }

    public function route(): void
    {
        $screen = (string) get_query_var(Routes::QUERY_VAR);
        if ($screen === '') {
            return;
        }
        self::sendNoCacheHeaders();

        $user = wp_get_current_user();
        $vendor = $user->exists() ? $this->vendors->findByUserId($user->ID) : null;

        if ($screen === 'registro') {
            if ($vendor !== null) {
                wp_safe_redirect(Routes::panelUrl());
                exit;
            }
            $this->renderRegister([], []);
            exit;
        }

        if (!$user->exists()) {
            $this->renderPage('guest', 'Tu panel de vendedor', ['user' => $user], false);
            exit;
        }
        if ($vendor === null) {
            $this->renderPage('no-store', 'Tu panel de vendedor', ['user' => $user], false);
            exit;
        }

        $this->renderPage('home', 'Inicio', [
            'user' => $user,
            'vendor' => $vendor,
            'documents' => $this->documents->forVendor((int) $vendor->id),
            'rules' => $this->rules->current(),
            'notice' => isset($_GET['enviado']) ? sanitize_key((string) $_GET['enviado']) : '',
        ], true);
        exit;
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, mixed>  $values
     */
    public function renderRegister(array $errors, array $values): void
    {
        self::sendNoCacheHeaders();
        $this->renderPage('register', 'Vende en Cheppi', [
            'user' => wp_get_current_user(),
            'errors' => $errors,
            'values' => $values,
        ], false);
    }

    /** @param array<string, mixed> $vars */
    private function renderPage(string $view, string $title, array $vars, bool $withShell): void
    {
        status_header(200);
        $vars['title'] = $title;
        $vars['view'] = $view;
        $vars['withShell'] = $withShell;
        $vars['assets'] = $this->assets;
        $vars['content'] = View::capture('panel/' . $view, $vars);
        echo View::capture('panel/layout', $vars); // phpcs:ignore WordPress.Security.EscapeOutput -- templates escape their own output
    }

    /** Panel pages are personal and carry nonces: never cache them (WordPress, LiteSpeed, browsers, proxies). */
    public static function sendNoCacheHeaders(): void
    {
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        nocache_headers();
        header('X-LiteSpeed-Cache-Control: no-cache');
        header('X-Robots-Tag: noindex, nofollow');
    }

    public static function initials(WP_User $user, ?Vendor $vendor = null): string
    {
        $name = $vendor?->storeName ?: ($user->display_name ?: $user->user_email);
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $letters = mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[1] ?? '', 0, 1);
        return mb_strtoupper($letters !== '' ? $letters : 'C');
    }
}
