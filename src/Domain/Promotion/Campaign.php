<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Promotion;

use DateTimeImmutable;
use DateTimeZone;

/**
 * A Cheppi campaign (e.g. "Cyber Wow"): vendors who join pay a lower commission while it runs.
 * Dates are whole days in the site's time zone; the end day is included.
 */
final class Campaign
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $description,
        public readonly DateTimeImmutable $startsOn,
        public readonly DateTimeImmutable $endsOn,
        public readonly int $commissionBps
    ) {
    }

    public function isActive(DateTimeImmutable $now): bool
    {
        $day = $now->setTimezone($this->startsOn->getTimezone())->format('Y-m-d');
        return $day >= $this->startsOn->format('Y-m-d') && $day <= $this->endsOn->format('Y-m-d');
    }

    public function isOver(DateTimeImmutable $now): bool
    {
        return $now->setTimezone($this->startsOn->getTimezone())->format('Y-m-d') > $this->endsOn->format('Y-m-d');
    }

    /**
     * Commission for a sale now: the lowest of the normal rate and the active campaigns the vendor joined.
     *
     * @param list<self> $joined
     */
    public static function commissionFor(int $normalBps, array $joined, DateTimeImmutable $now): int
    {
        $bps = $normalBps;
        foreach ($joined as $campaign) {
            if ($campaign->isActive($now)) {
                $bps = min($bps, $campaign->commissionBps);
            }
        }
        return $bps;
    }

    /**
     * @param array<string, mixed> $input name, description, starts_on, ends_on (Y-m-d), commission (percent)
     * @throws PromotionValidationException
     */
    public static function fromInput(array $input, DateTimeZone $tz, string $id = ''): self
    {
        $errors = [];
        $name = trim((string) ($input['name'] ?? ''));
        if (mb_strlen($name) < 3 || mb_strlen($name) > 60) {
            $errors['name'] = 'Nombre de 3 a 60 caracteres.';
        }
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($input['starts_on'] ?? ''), $tz) ?: null;
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($input['ends_on'] ?? ''), $tz) ?: null;
        if ($start === null) {
            $errors['starts_on'] = 'Fecha de inicio no válida.';
        }
        if ($end === null || ($start !== null && $end < $start)) {
            $errors['ends_on'] = 'La fecha de fin debe ser igual o posterior al inicio.';
        }
        $percent = str_replace(',', '.', trim((string) ($input['commission'] ?? '')));
        if (!preg_match('/^\d{1,2}(\.\d{1,2})?$/', $percent) || (float) $percent > 50) {
            $errors['commission'] = 'Comisión entre 0 y 50 %.';
        }
        if ($errors !== []) {
            throw new PromotionValidationException($errors);
        }
        $id = $id !== '' ? $id : substr(strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $name)), 0, 30) . '-' . $start->format('ymd');
        return new self(trim($id, '-'), $name, mb_substr(trim((string) ($input['description'] ?? '')), 0, 300), $start, $end, (int) round((float) $percent * 100));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'starts_on' => $this->startsOn->format('Y-m-d'),
            'ends_on' => $this->endsOn->format('Y-m-d'),
            'commission_bps' => $this->commissionBps,
        ];
    }

    /** @param array<string, mixed> $row */
    public static function fromArray(array $row, DateTimeZone $tz): self
    {
        return new self(
            (string) $row['id'],
            (string) $row['name'],
            (string) ($row['description'] ?? ''),
            new DateTimeImmutable((string) $row['starts_on'], $tz),
            new DateTimeImmutable((string) $row['ends_on'], $tz),
            (int) $row['commission_bps']
        );
    }
}
