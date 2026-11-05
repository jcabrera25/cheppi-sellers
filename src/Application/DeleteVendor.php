<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Application;

use Cheppi\Sellers\Domain\Vendor\Vendor;
use DomainException;

/**
 * Use case: an admin removes a vendor and its documents.
 * Vendors with sales or payouts cannot be deleted (accounting records must stay); suspend them instead.
 * The user account is kept: it may also be a customer.
 */
final class DeleteVendor
{
    public function __construct(
        private readonly VendorRepository $vendors,
        private readonly DocumentStore $documents,
        private readonly VendorActivity $activity
    ) {
    }

    /** @throws DomainException */
    public function __invoke(int $vendorId, string $confirmation): Vendor
    {
        $vendor = $this->vendors->find($vendorId) ?? throw new DomainException('Vendedor no encontrado.');
        if (mb_strtolower(trim($confirmation)) !== mb_strtolower($vendor->storeName)) {
            throw new DomainException('Escribe el nombre exacto de la tienda para confirmar.');
        }
        if ($this->activity->hasFinancialHistory($vendorId)) {
            throw new DomainException('Esta tienda tiene ventas o pagos registrados y no se puede eliminar. Suspéndela.');
        }
        $this->documents->deleteAllFor($vendorId);
        $this->vendors->delete($vendorId);
        return $vendor;
    }
}
