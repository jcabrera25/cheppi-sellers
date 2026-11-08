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
        public readonly ?DateTimeImmutable $to = null,
        public readonly ?string $customer = null,
        /** @var list<int>|null orders of that customer, once resolved (null = no restriction) */
        public readonly ?array $orderIds = null
    ) {
    }

    /** Opaque key of a customer (their e-mail never goes in URLs): 16 hex characters. */
    public static function isCustomerKey(string $key): bool
    {
        return (bool) preg_match('/^[a-f0-9]{16}$/', $key);
    }

    /** @param list<int> $orderIds */
    public function withOrderIds(array $orderIds): self
    {
        return new self($this->vendorId, $this->status, $this->from, $this->to, $this->customer, array_values(array_map('intval', $orderIds)));
    }

    /**
     * From the query string: estado, desde, hasta (Y-m-d), cliente and, when allowed, tienda.
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
        $customer = strtolower((string) ($query['cliente'] ?? ''));
        return new self($vendor, FulfillmentStatus::tryFrom((string) ($query['estado'] ?? '')), $from, $to, self::isCustomerKey($customer) ? $customer : null);
    }

    /** @return array<string, string> the same filters as query args (for links and exports) */
    public function toQuery(bool $withVendor): array
    {
        return array_filter([
            'estado' => $this->status?->value ?? '',
            'desde' => $this->from?->format('Y-m-d') ?? '',
            'hasta' => $this->to?->format('Y-m-d') ?? '',
            'tienda' => $withVendor && $this->vendorId ? (string) $this->vendorId : '',
            'cliente' => $this->customer ?? '',
        ]);
    }
}
