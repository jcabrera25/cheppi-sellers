<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress;

use Cheppi\Sellers\Infrastructure\WordPress\Admin\BusinessRulesPage;
use Cheppi\Sellers\Infrastructure\WordPress\Persistence\WpBusinessRulesRepository;
use Cheppi\Sellers\Infrastructure\WordPress\Rest\RulesController;

/**
 * Composition root: wires domain services to WordPress. Holds no business logic.
 */
final class Plugin
{
    private static ?self $instance = null;
    private bool $booted = false;
    private readonly WpBusinessRulesRepository $rules;
    private readonly Assets $assets;

    private function __construct()
    {
        $this->rules = new WpBusinessRulesRepository();
        $this->assets = new Assets();
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function rules(): WpBusinessRulesRepository
    {
        return $this->rules;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        load_plugin_textdomain('cheppi-sellers', false, dirname(plugin_basename(CHEPPI_SELLERS_FILE)) . '/languages');

        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', static function (): void {
                echo '<div class="notice notice-error"><p>'
                    . esc_html__('Cheppi Sellers necesita WooCommerce activo.', 'cheppi-sellers')
                    . '</p></div>';
            });
            return;
        }

        Installer::maybeUpgrade();

        if (is_admin()) {
            (new BusinessRulesPage($this->rules, $this->assets))->register();
        }

        add_action('rest_api_init', function (): void {
            (new RulesController($this->rules))->register();
        });
    }
}
