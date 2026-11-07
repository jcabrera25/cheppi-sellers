<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Admin;

use Cheppi\Sellers\Application\BusinessRulesRepository;
use Cheppi\Sellers\Infrastructure\WordPress\Assets;
use Cheppi\Sellers\Infrastructure\WordPress\Catalog\VendorStats;
use Cheppi\Sellers\Infrastructure\WordPress\Persistence\WpVendorRepository;

/** Cheppi → Métricas: marketplace sales, commission, fulfilment and the vendors that need a nudge. */
final class MetricsPage extends AdminScreen
{
    public const SLUG = 'cheppi-metrics';

    public function __construct(
        Assets $assets,
        private readonly VendorStats $stats,
        private readonly WpVendorRepository $vendors,
        private readonly BusinessRulesRepository $rules
    ) {
        parent::__construct($assets);
    }

    protected function slug(): string
    {
        return self::SLUG;
    }

    protected function menuTitle(): string
    {
        return __('Métricas', 'cheppi-sellers');
    }

    protected function position(): int
    {
        return 0;
    }

    public function register(): void
    {
        $this->hooks();
    }

    public function render(): void
    {
        $this->authorize();
        $days = (int) ($_GET['dias'] ?? 30);
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;
        $dispatch = $this->rules->current()->dispatchHours;
        $top = $this->stats->topVendors($days);
        $late = $this->stats->lateFulfillments($dispatch);
        $ids = array_unique(array_merge(array_column($top, 'vendor_id'), array_column($late, 'vendor_id')));
        $names = [];
        foreach ($ids as $id) {
            $names[$id] = $this->vendors->find((int) $id)?->storeName ?? '#' . $id;
        }
        $this->view('metrics', [
            'days' => $days,
            'm' => $this->stats->metrics(null, $days, $dispatch),
            'salesByDay' => $this->stats->salesByDay(null, $days),
            'top' => $top,
            'late' => $late,
            'names' => $names,
            'dispatch' => $dispatch,
            'counts' => $this->vendors->countByStatus(),
            'pageUrl' => fn (array $args): string => $this->url($args),
        ]);
    }
}
