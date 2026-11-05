<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Application;

use Cheppi\Sellers\Domain\Vendor\StoreSlug;
use Cheppi\Sellers\Domain\Vendor\Vendor;
use Cheppi\Sellers\Domain\Vendor\VendorApplication;
use Cheppi\Sellers\Domain\Vendor\VendorValidationException;
use DateTimeImmutable;
use DomainException;

/**
 * Use case: an admin corrects a vendor's business data and store address.
 */
final class UpdateVendorProfile
{
    public function __construct(private readonly VendorRepository $vendors)
    {
    }

    /**
     * @param array<string, mixed> $input business fields + optional "store_slug"
     * @throws VendorValidationException|DomainException
     */
    public function __invoke(int $vendorId, array $input): Vendor
    {
        $vendor = $this->vendors->find($vendorId) ?? throw new DomainException('Vendedor no encontrado.');
        $profile = VendorApplication::profile($input);

        $errors = [];
        if ($this->vendors->rucExists($profile->ruc->value, $vendorId)) {
            $errors['ruc'] = 'Otro vendedor ya usa este RUC.';
        }

        $requested = trim((string) ($input['store_slug'] ?? ''));
        $slug = $requested === '' ? $vendor->storeSlug : StoreSlug::fromName($requested);
        if ($slug !== $vendor->storeSlug && $this->vendors->slugExists($slug, $vendorId)) {
            $errors['store_slug'] = 'Esa dirección de tienda ya está en uso.';
        }
        if ($errors !== []) {
            throw new VendorValidationException($errors);
        }

        return $this->vendors->save($vendor->withProfile($profile, $slug, new DateTimeImmutable('now')));
    }
}
