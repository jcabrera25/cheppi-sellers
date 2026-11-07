<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Promotions;

use Cheppi\Sellers\Domain\Promotion\Campaign;
use DateTimeImmutable;

/** Campaigns live in one autoloaded option (a handful per year), so checkout reads them without a query. */
final class Campaigns
{
    public const OPTION = 'cheppi_campaigns';

    /** @return list<Campaign> newest first */
    public function all(): array
    {
        $tz = wp_timezone();
        $rows = get_option(self::OPTION, []);
        $list = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (is_array($row) && isset($row['id'], $row['name'], $row['starts_on'], $row['ends_on'], $row['commission_bps'])) {
                $list[] = Campaign::fromArray($row, $tz);
            }
        }
        usort($list, static fn (Campaign $a, Campaign $b): int => $b->startsOn <=> $a->startsOn);
        return $list;
    }

    public function find(string $id): ?Campaign
    {
        foreach ($this->all() as $c) {
            if ($c->id === $id) {
                return $c;
            }
        }
        return null;
    }

    /** @param list<string> $ids @return list<Campaign> */
    public function byIds(array $ids): array
    {
        return array_values(array_filter($this->all(), static fn (Campaign $c): bool => in_array($c->id, $ids, true)));
    }

    /** @return list<Campaign> running or upcoming */
    public function open(DateTimeImmutable $now): array
    {
        return array_values(array_filter($this->all(), static fn (Campaign $c): bool => !$c->isOver($now)));
    }

    public function save(Campaign $campaign): void
    {
        $rows = array_filter((array) get_option(self::OPTION, []), static fn ($r): bool => is_array($r) && ($r['id'] ?? '') !== $campaign->id);
        $rows[] = $campaign->toArray();
        update_option(self::OPTION, array_values($rows), true);
    }

    public function delete(string $id): void
    {
        $rows = array_filter((array) get_option(self::OPTION, []), static fn ($r): bool => is_array($r) && ($r['id'] ?? '') !== $id);
        update_option(self::OPTION, array_values($rows), true);
    }
}
