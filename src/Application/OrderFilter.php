<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Application;

use Cheppi\Sellers\Domain\Order\FulfillmentStatus;
use DateTimeImmutable;

/** Filters of the orders screens and their exports (both dates included, in the site's time zone). */
final class OrderFilter
{
    public function __construct(
        public readonly ?int $vendorId = null,
        public readonly ?FulfillmentStatus $status = null,
        public readonly ?DateTimeImmutable $from = null,
        public readonly ?DateTimeImmutable $to = null
    ) {
    }

    /**
     * From the query string: estado, desde, hasta (Y-m-d) and, when allowed, tienda.
     *
     * @param array<string, mixed> $query
     */
    public static function fromQuery(array $query, ?int $forcedVendorId, \DateTimeZone $tz): self
    {
        $date = static function (mixed $value) use ($tz): ?DateTimeImmutable {
            $d = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value, $tz);
            return $d ?: null;
        };
        $from = $date($query['desde'] ?? '');
        $to = $date($query['hasta'] ?? '');
        if ($from && $to && $to < $from) {
            [$from, $to] = [$to, $from];
        }
        $vendor = $forcedVendorId ?? ((int) ($query['tienda'] ?? 0) ?: null);
        return new self($vendor, FulfillmentStatus::tryFrom((string) ($query['estado'] ?? '')), $from, $to);
    }

    /** @return array<string, string> the same filters as query args (for links and exports) */
    public function toQuery(bool $withVendor): array
    {
        return array_filter([
            'estado' => $this->status?->value ?? '',
            'desde' => $this->from?->format('Y-m-d') ?? '',
            'hasta' => $this->to?->format('Y-m-d') ?? '',
            'tienda' => $withVendor && $this->vendorId ? (string) $this->vendorId : '',
        ]);
    }
}
