<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Vendor;

use DateTimeImmutable;
use DomainException;

/**
 * A seller on Cheppi. Immutable: changes return a new instance.
 */
final class Vendor
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $userId,
        public readonly string $storeName,
        public readonly string $storeSlug,
        public readonly VendorStatus $status,
        public readonly string $planId,
        public readonly ?int $commissionOverrideBps,
        public readonly string $ruc,
        public readonly string $legalName,
        public readonly string $phone,
        public readonly string $category,
        public readonly string $district,
        public readonly string $instagram,
        public readonly string $website,
        public readonly string $statusNote,
        public readonly DateTimeImmutable $createdAt,
        public readonly DateTimeImmutable $updatedAt,
        public readonly ?DateTimeImmutable $approvedAt = null
    ) {
    }

    public static function apply(
        int $userId,
        VendorApplication $application,
        string $storeSlug,
        string $planId,
        DateTimeImmutable $now
    ): self {
        return new self(
            null,
            $userId,
            $application->storeName,
            $storeSlug,
            VendorStatus::Pending,
            $planId,
            null,
            $application->ruc->value,
            $application->legalName,
            $application->phone,
            $application->category,
            $application->district,
            $application->instagram,
            $application->website,
            '',
            $now,
            $now
        );
    }

    /** @throws DomainException when the transition is not allowed */
    public function transitionTo(VendorStatus $next, string $note, DateTimeImmutable $now): self
    {
        if (!$this->status->canTransitionTo($next)) {
            throw new DomainException(sprintf(
                'No se puede pasar de «%s» a «%s».',
                $this->status->label(),
                $next->label()
            ));
        }
        if (in_array($next, [VendorStatus::NeedsInfo, VendorStatus::Rejected, VendorStatus::Suspended], true) && trim($note) === '') {
            throw new DomainException('Escribe el motivo para el vendedor.');
        }
        return $this->with([
            'status' => $next,
            'statusNote' => trim($note),
            'updatedAt' => $now,
            'approvedAt' => $next === VendorStatus::Approved ? ($this->approvedAt ?? $now) : $this->approvedAt,
        ]);
    }

    public function withCommercialTerms(string $planId, ?int $commissionOverrideBps, DateTimeImmutable $now): self
    {
        if ($commissionOverrideBps !== null && ($commissionOverrideBps < 0 || $commissionOverrideBps > 10000)) {
            throw new DomainException('La comisión del vendedor debe estar entre 0% y 100%.');
        }
        return $this->with(['planId' => $planId, 'commissionOverrideBps' => $commissionOverrideBps, 'updatedAt' => $now]);
    }

    /** Business data edited by an admin. Status, plan and history are untouched. */
    public function withProfile(VendorApplication $profile, string $storeSlug, DateTimeImmutable $now): self
    {
        return $this->with([
            'storeName' => $profile->storeName,
            'storeSlug' => $storeSlug,
            'ruc' => $profile->ruc->value,
            'legalName' => $profile->legalName,
            'phone' => $profile->phone,
            'category' => $profile->category,
            'district' => $profile->district,
            'instagram' => $profile->instagram,
            'website' => $profile->website,
            'updatedAt' => $now,
        ]);
    }

    public function withId(int $id): self
    {
        return $this->with(['id' => $id]);
    }

    /** @param array<string, mixed> $changes */
    private function with(array $changes): self
    {
        $values = get_object_vars($this);
        foreach ($changes as $key => $value) {
            $values[$key] = $value;
        }
        return new self(...$values);
    }
}
