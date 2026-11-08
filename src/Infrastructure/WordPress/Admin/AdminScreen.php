<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Admin;

use Cheppi\Sellers\Infrastructure\WordPress\Assets;
use Cheppi\Sellers\Infrastructure\WordPress\Capabilities;

/**
 * Shared plumbing for Cheppi admin screens: menu entry, permission check, assets, templates and flash messages.
 */
abstract class AdminScreen
{
    protected string $hook = '';

    public function __construct(protected readonly Assets $assets)
    {
    }

    abstract protected function slug(): string;

    abstract protected function menuTitle(): string;

    abstract public function render(): void;

    protected function position(): int
    {
        return 5;
    }

    public function addMenu(): void
    {
        $this->hook = (string) add_submenu_page(BusinessRulesPage::SLUG, $this->menuTitle(), $this->menuTitle(), Capabilities::MANAGE, $this->slug(), [$this, 'render'], $this->position());
    }

    public function enqueue(string $hook): void
    {
        if ($hook === $this->hook) {
            $this->assets->enqueue('admin');
        }
    }

    protected function hooks(): void
    {
        add_action('admin_menu', [$this, 'addMenu'], 12);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    protected function url(array $args = []): string
    {
        return \Cheppi\Sellers\Infrastructure\WordPress\Front\ManageContext::url($this->slug(), $args);
    }

    protected function authorize(string $nonceAction = ''): void
    {
        if (!current_user_can(Capabilities::MANAGE) || ($nonceAction !== '' && !check_admin_referer($nonceAction))) {
            wp_die(esc_html__('No tienes permiso para hacer esto.', 'cheppi-sellers'), 403);
        }
    }

    /** @param array<string, mixed> $vars */
    protected function view(string $template, array $vars): void
    {
        extract($vars, EXTR_SKIP);
        require CHEPPI_SELLERS_DIR . '/templates/admin/' . $template . '.php';
    }

    /** @param array<string, mixed> $data */
    protected function flash(array $data, string $redirect): never
    {
        set_transient('cheppi_admin_flash_' . get_current_user_id(), $data, 300);
        wp_safe_redirect($redirect);
        exit;
    }

    /** @return array<string, mixed> */
    protected function takeFlash(): array
    {
        $key = 'cheppi_admin_flash_' . get_current_user_id();
        $flash = get_transient($key);
        if ($flash !== false) {
            delete_transient($key);
        }
        return is_array($flash) ? $flash : [];
    }
}
