<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Shipping;

use Cheppi\Sellers\Domain\Money\Money;

/**
 * A vendor-defined zone: a set of districts, a price, an ETA and an optional free-shipping threshold.
 */
final class DeliveryZone
{
    /** @param list<string> $districtCodes */
    public function __construct(
        public readonly string $name,
        public readonly array $districtCodes,
        public readonly Money $cost,
        public readonly int $etaDays,
        public readonly ?Money $freeFrom = null
    ) {
    }

    public const ANYWHERE = '*';
    public const MAX_ZONES = 6;

    public function covers(string $districtCode): bool
    {
        return in_array($districtCode, $this->districtCodes, true) || in_array(self::ANYWHERE, $this->districtCodes, true);
    }

    /** @return array{name: string, districts: list<string>, cost: int, eta_days: int, free_from: ?int} */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'districts' => $this->districtCodes,
            'cost' => $this->cost->cents,
            'eta_days' => $this->etaDays,
            'free_from' => $this->freeFrom?->cents,
        ];
    }

    /** @param array<string, mixed> $row as stored by toArray() */
    public static function fromArray(array $row): self
    {
        return new self(
            (string) ($row['name'] ?? ''),
            array_values(array_map('strval', (array) ($row['districts'] ?? []))),
            Money::ofCents((int) ($row['cost'] ?? 0)),
            (int) ($row['eta_days'] ?? 1),
            isset($row['free_from']) ? Money::ofCents((int) $row['free_from']) : null
        );
    }

    /**
     * Zones from the panel form. Rows without districts are ignored; a district can belong to one zone only
     * (the first one wins at checkout, so a duplicate would be silently unreachable).
     *
     * @param list<array<string, mixed>> $rows
     * @return list<self>
     * @throws ZoneValidationException with keys like "zones.0.cost"
     */
    public static function listFromInput(array $rows): array
    {
        $zones = [];
        $errors = [];
        $seen = [];
        foreach (array_slice(array_values($rows), 0, self::MAX_ZONES) as $i => $row) {
            $districts = array_values(array_unique(array_filter(
                array_map('strval', (array) ($row['districts'] ?? [])),
                static fn (string $c): bool => $c === self::ANYWHERE || LimaDistricts::isValid($c)
            )));
            if ($districts === []) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 40) {
                $errors["zones.$i.name"] = 'Ponle un nombre de hasta 40 caracteres.';
            }
            $cost = self::money($row['cost'] ?? '');
            if ($cost === null) {
                $errors["zones.$i.cost"] = 'Escribe la tarifa (0 si es gratis).';
            }
            $eta = filter_var($row['eta_days'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 15]]);
            if ($eta === false) {
                $errors["zones.$i.eta_days"] = 'Días de entrega entre 0 y 15.';
            }
            $freeFrom = null;
            if (trim((string) ($row['free_from'] ?? '')) !== '') {
                $freeFrom = self::money($row['free_from']);
                if ($freeFrom === null || $freeFrom->cents === 0) {
                    $errors["zones.$i.free_from"] = 'Monto mayor a 0, o déjalo vacío.';
                }
            }
            foreach ($districts as $code) {
                if (isset($seen[$code])) {
                    $errors["zones.$i.districts"] = sprintf('%s ya está en otra zona.', $code === self::ANYWHERE ? 'El resto de distritos' : LimaDistricts::label($code));
                }
                $seen[$code] = true;
            }
            if ($cost !== null && $eta !== false) {
                $zones[] = new self($name, $districts, $cost, (int) $eta, $freeFrom);
            }
        }
        if ($errors !== []) {
            throw new ZoneValidationException($errors);
        }
        // "Resto de distritos" must be checked last so specific zones win.
        usort($zones, static fn (self $a, self $b): int => (int) in_array(self::ANYWHERE, $a->districtCodes, true) <=> (int) in_array(self::ANYWHERE, $b->districtCodes, true));
        return $zones;
    }

    private static function money(mixed $value): ?Money
    {
        $value = str_replace(',', '.', trim((string) $value));
        if (!preg_match('/^\d{1,5}(\.\d{1,2})?$/', $value)) {
            return null;
        }
        return Money::ofDecimal($value);
    }
}
