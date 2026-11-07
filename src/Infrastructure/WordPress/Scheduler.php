<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress;

use Cheppi\Sellers\Application\BusinessRulesRepository;
use Cheppi\Sellers\Application\GeneratePayouts;
use Cheppi\Sellers\Domain\Payout\PayoutSchedule;
use Cheppi\Sellers\Infrastructure\WordPress\Promotions\FeaturedProducts;
use DateTimeImmutable;

/**
 * One daily job (WP-Cron, ~06:00 Lima): run the cut-off on payout days and end expired featured products.
 * On hosting with real cron, point it to wp-cron.php so it does not depend on visits.
 */
final class Scheduler
{
    public const HOOK = 'cheppi_sellers_daily';

    public function __construct(
        private readonly GeneratePayouts $generate,
        private readonly BusinessRulesRepository $rules,
        private readonly FeaturedProducts $featured
    ) {
    }

    public function register(): void
    {
        add_action(self::HOOK, [$this, 'run']);
        add_action('init', static function (): void {
            if (!wp_next_scheduled(self::HOOK)) {
                $first = (new DateTimeImmutable('tomorrow 06:00', wp_timezone()))->getTimestamp();
                wp_schedule_event($first, 'daily', self::HOOK);
            }
        }, 30);
    }

    public static function unschedule(): void
    {
        wp_clear_scheduled_hook(self::HOOK);
    }

    public function run(): void
    {
        $today = new DateTimeImmutable('today', wp_timezone());
        if ((new PayoutSchedule($this->rules->current()))->nextCutoff($today)->format('Y-m-d') === $today->format('Y-m-d')) {
            ($this->generate)(new DateTimeImmutable('now', wp_timezone()), new DateTimeImmutable('now'));
        }
        $this->featured->expire();
    }
}
