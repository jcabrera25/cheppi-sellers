<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Admin;

use Cheppi\Sellers\Domain\Promotion\Campaign;
use Cheppi\Sellers\Domain\Promotion\PromotionValidationException;
use Cheppi\Sellers\Infrastructure\WordPress\Assets;
use Cheppi\Sellers\Infrastructure\WordPress\Installer;
use Cheppi\Sellers\Infrastructure\WordPress\Promotions\Campaigns;

/** Cheppi → Campañas: dated campaigns with a reduced commission that vendors can join. */
final class CampaignsPage extends AdminScreen
{
    public const SLUG = 'cheppi-campaigns';
    public const ACTION_SAVE = 'cheppi_campaign_save';
    public const ACTION_DELETE = 'cheppi_campaign_delete';

    public function __construct(Assets $assets, private readonly Campaigns $campaigns)
    {
        parent::__construct($assets);
    }

    protected function slug(): string
    {
        return self::SLUG;
    }

    protected function menuTitle(): string
    {
        return __('Campañas', 'cheppi-sellers');
    }

    protected function position(): int
    {
        return 3;
    }

    public function register(): void
    {
        $this->hooks();
        add_action('admin_post_' . self::ACTION_SAVE, [$this, 'save']);
        add_action('admin_post_' . self::ACTION_DELETE, [$this, 'delete']);
    }

    public function render(): void
    {
        $this->authorize();
        global $wpdb;
        $table = Installer::tables()['vendors'];
        $members = [];
        foreach ($this->campaigns->all() as $c) {
            $members[$c->id] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE FIND_IN_SET(%s, campaigns)", $c->id));
        }
        $this->view('campaigns', [
            'campaigns' => $this->campaigns->all(),
            'members' => $members,
            'flash' => $this->takeFlash(),
        ]);
    }

    public function save(): void
    {
        $this->authorize(self::ACTION_SAVE);
        $input = array_map('sanitize_text_field', wp_unslash(array_intersect_key($_POST, array_flip(['name', 'description', 'starts_on', 'ends_on', 'commission']))));
        try {
            $campaign = Campaign::fromInput($input, wp_timezone());
            $this->campaigns->save($campaign);
            $this->flash(['type' => 'success', 'message' => sprintf(__('Campaña «%s» guardada. Los vendedores la ven en Promociones.', 'cheppi-sellers'), $campaign->name)], $this->url());
        } catch (PromotionValidationException $e) {
            $this->flash(['type' => 'error', 'message' => implode(' ', $e->errors), 'values' => $input], $this->url());
        }
    }

    public function delete(): void
    {
        $id = sanitize_key((string) ($_POST['campaign'] ?? ''));
        $this->authorize(self::ACTION_DELETE . $id);
        $this->campaigns->delete($id);
        $this->flash(['type' => 'success', 'message' => __('Campaña eliminada. Las ventas ya hechas mantienen su comisión.', 'cheppi-sellers')], $this->url());
    }
}
