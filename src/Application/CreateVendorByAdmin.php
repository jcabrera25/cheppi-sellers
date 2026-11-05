<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Application;

use Cheppi\Sellers\Domain\Vendor\DocumentType;
use Cheppi\Sellers\Domain\Vendor\StoreSlug;
use Cheppi\Sellers\Domain\Vendor\Vendor;
use Cheppi\Sellers\Domain\Vendor\VendorApplication;
use Cheppi\Sellers\Domain\Vendor\VendorStatus;
use Cheppi\Sellers\Domain\Vendor\VendorValidationException;
use DateTimeImmutable;

/**
 * Use case: an admin registers a brand directly (no public form, documents optional,
 * can start already approved, with its plan and special commission).
 */
final class CreateVendorByAdmin
{
    public function __construct(
        private readonly VendorRepository $vendors,
        private readonly DocumentStore $documents,
        private readonly BusinessRulesRepository $rules
    ) {
    }

    /**
     * @param array<string, mixed>        $input business fields
     * @param array<string, UploadedFile> $files keyed by DocumentType value (optional)
     * @throws VendorValidationException
     */
    public function __invoke(
        int $userId,
        array $input,
        array $files,
        VendorStatus $initialStatus,
        string $planId,
        ?int $commissionOverrideBps
    ): Vendor {
        $errors = [];
        if ($this->vendors->findByUserId($userId) !== null) {
            $errors['email'] = 'Esa cuenta ya tiene una tienda registrada.';
        }
        if (!in_array($initialStatus, [VendorStatus::Pending, VendorStatus::Approved], true)) {
            $errors['status'] = 'Un vendedor nuevo empieza en revisión o aprobado.';
        }
        if ($this->rules->current()->plan($planId) === null) {
            $errors['plan_id'] = 'El plan elegido no existe en las reglas de negocio.';
        }
        if ($commissionOverrideBps !== null && ($commissionOverrideBps < 0 || $commissionOverrideBps > 10000)) {
            $errors['commission_override'] = 'La comisión especial debe estar entre 0% y 100%.';
        }
        $valid = [];
        foreach ($files as $typeValue => $file) {
            $type = DocumentType::tryFrom((string) $typeValue);
            if ($type === null) {
                continue;
            }
            try {
                $file->assertAcceptable('doc_' . $type->value);
                $valid[] = $type;
            } catch (VendorValidationException $e) {
                $errors += $e->errors;
            }
        }

        try {
            $profile = VendorApplication::profile($input);
        } catch (VendorValidationException $e) {
            throw new VendorValidationException($errors + $e->errors);
        }
        if ($this->vendors->rucExists($profile->ruc->value)) {
            $errors['ruc'] = 'Este RUC ya tiene una tienda en Cheppi.';
        }
        if ($errors !== []) {
            throw new VendorValidationException($errors);
        }

        $now = new DateTimeImmutable('now');
        $slug = StoreSlug::unique($profile->storeName, fn (string $s): bool => $this->vendors->slugExists($s));
        $vendor = Vendor::apply($userId, $profile, $slug, $planId, $now)
            ->withCommercialTerms($planId, $commissionOverrideBps, $now);
        if ($initialStatus === VendorStatus::Approved) {
            $vendor = $vendor->transitionTo(VendorStatus::Approved, '', $now);
        }
        $vendor = $this->vendors->save($vendor);

        foreach ($valid as $type) {
            $this->documents->store((int) $vendor->id, $type, $files[$type->value]);
        }
        return $vendor;
    }
}
