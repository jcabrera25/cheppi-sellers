<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Admin;

use Cheppi\Sellers\Application\BusinessRulesRepository;
use Cheppi\Sellers\Application\UpdateBusinessRules;
use Cheppi\Sellers\Domain\Rules\RulesValidationException;
use Cheppi\Sellers\Infrastructure\WordPress\Assets;
use Cheppi\Sellers\Infrastructure\WordPress\Capabilities;

/**
 * wp-admin → Cheppi → Reglas de negocio. Server-rendered; a tiny module adds/removes repeatable rows.
 */
final class BusinessRulesPage
{
    public const SLUG = 'cheppi-rules';
    private const ACTION = 'cheppi_save_rules';
    private const NONCE = 'cheppi_rules_nonce';

    private string $hook = '';

    public function __construct(
        private readonly BusinessRulesRepository $repository,
        private readonly Assets $assets
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenu']);
        add_action('admin_post_' . self::ACTION, [$this, 'handleSave']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function addMenu(): void
    {
        add_menu_page(
            __('Cheppi Market', 'cheppi-sellers'),
            __('Cheppi', 'cheppi-sellers'),
            Capabilities::MANAGE,
            self::SLUG,
            [$this, 'render'],
            'dashicons-store',
            56
        );
        $this->hook = (string) add_submenu_page(
            self::SLUG,
            __('Reglas de negocio', 'cheppi-sellers'),
            __('Reglas de negocio', 'cheppi-sellers'),
            Capabilities::MANAGE,
            self::SLUG,
            [$this, 'render']
        );
    }

    public function enqueue(string $hook): void
    {
        if ($hook !== $this->hook) {
            return; // nothing from the plugin loads on other screens
        }
        $this->assets->enqueue('admin-rules');
    }

    public function render(): void
    {
        if (!current_user_can(Capabilities::MANAGE)) {
            wp_die(esc_html__('No tienes permiso para ver esta página.', 'cheppi-sellers'));
        }
        $flash = get_transient($this->flashKey());
        delete_transient($this->flashKey());

        $errors = is_array($flash) && isset($flash['errors']) ? (array) $flash['errors'] : [];
        $form = is_array($flash) && isset($flash['form']) ? (array) $flash['form'] : RulesFormMapper::toForm($this->repository->current());
        $saved = is_array($flash) && !empty($flash['saved']);
        $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'fields' => 'id=>name']);
        $categories = is_array($categories) ? $categories : [];
        $history = $this->repository->history(10);
        $action = self::ACTION;
        $nonce = self::NONCE;

        require CHEPPI_SELLERS_DIR . '/templates/admin/business-rules.php';
    }

    public function handleSave(): void
    {
        if (!current_user_can(Capabilities::MANAGE)) {
            wp_die(esc_html__('No tienes permiso para hacer esto.', 'cheppi-sellers'), 403);
        }
        check_admin_referer(self::ACTION, self::NONCE);

        $post = wp_unslash($_POST);
        $form = is_array($post['rules'] ?? null) ? self::sanitizeTree($post['rules']) : [];

        try {
            (new UpdateBusinessRules($this->repository))(RulesFormMapper::fromForm($form), get_current_user_id());
            set_transient($this->flashKey(), ['saved' => true], 60);
        } catch (RulesValidationException $e) {
            set_transient($this->flashKey(), ['errors' => $e->errors, 'form' => $form], 300);
        }

        wp_safe_redirect(admin_url('admin.php?page=' . self::SLUG));
        exit;
    }

    private function flashKey(): string
    {
        return 'cheppi_rules_flash_' . get_current_user_id();
    }

    /**
     * Sanitizes nested form input as plain text, keeping structure.
     *
     * @param array<mixed> $tree
     * @return array<mixed>
     */
    private static function sanitizeTree(array $tree): array
    {
        $clean = [];
        foreach ($tree as $key => $value) {
            $key = is_int($key) ? $key : sanitize_key((string) $key);
            $clean[$key] = is_array($value) ? self::sanitizeTree($value) : sanitize_text_field((string) $value);
        }
        return $clean;
    }
}
