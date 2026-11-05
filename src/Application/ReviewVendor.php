<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Application;

use Cheppi\Sellers\Domain\Vendor\Vendor;
use Cheppi\Sellers\Domain\Vendor\VendorStatus;
use DateTimeImmutable;
use DomainException;

/**
 * Use case: an admin approves, observes, rejects or suspends a vendor, and/or adjusts plan and commission.
 */
final class ReviewVendor
{
    public function __construct(
        private readonly VendorRepository $vendors,
        private readonly BusinessRulesRepository $rules,
        private readonly VendorNotifier $notifier
    ) {
    }

    /** @throws DomainException */
    public function __invoke(
        int $vendorId,
        ?VendorStatus $nextStatus,
        string $note,
        string $planId,
        ?int $commissionOverrideBps
    ): Vendor {
        $vendor = $this->vendors->find($vendorId) ?? throw new DomainException('Vendedor no encontrado.');
        $now = new DateTimeImmutable('now');

        if ($this->rules->current()->plan($planId) === null) {
            throw new DomainException('El plan elegido no existe en las reglas de negocio.');
        }
        $vendor = $vendor->withCommercialTerms($planId, $commissionOverrideBps, $now);

        $statusChanged = false;
        if ($nextStatus !== null && $nextStatus !== $vendor->status) {
            $vendor = $vendor->transitionTo($nextStatus, $note, $now);
            $statusChanged = true;
        }

        $vendor = $this->vendors->save($vendor);
        if ($statusChanged) {
            $this->notifier->statusChanged($vendor);
        }
        return $vendor;
    }
}
