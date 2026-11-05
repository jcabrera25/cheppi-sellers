<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Application;

use Cheppi\Sellers\Domain\Vendor\DocumentType;
use Cheppi\Sellers\Domain\Vendor\StoreSlug;
use Cheppi\Sellers\Domain\Vendor\Vendor;
use Cheppi\Sellers\Domain\Vendor\VendorApplication;
use Cheppi\Sellers\Domain\Vendor\VendorValidationException;
use DateTimeImmutable;

/**
 * Use case: a user applies to sell. Validates everything before writing anything.
 */
final class SubmitVendorApplication
{
    public function __construct(
        private readonly VendorRepository $vendors,
        private readonly DocumentStore $documents,
        private readonly BusinessRulesRepository $rules,
        private readonly VendorNotifier $notifier
    ) {
    }

    /**
     * @param array<string, mixed>        $input
     * @param array<string, UploadedFile> $files keyed by DocumentType value
     * @throws VendorValidationException
     */
    public function __invoke(int $userId, array $input, array $files, bool $termsAccepted): Vendor
    {
        if ($this->vendors->findByUserId($userId) !== null) {
            throw new VendorValidationException(['account' => 'Tu cuenta ya tiene una tienda registrada.']);
        }

        $provided = [];
        $fileErrors = [];
        foreach ($files as $typeValue => $file) {
            $type = DocumentType::tryFrom((string) $typeValue);
            if ($type === null) {
                continue;
            }
            try {
                $file->assertAcceptable('doc_' . $type->value);
                $provided[] = $type;
            } catch (VendorValidationException $e) {
                $fileErrors += $e->errors;
            }
        }

        try {
            $application = VendorApplication::fromArray($input, $provided, $termsAccepted);
        } catch (VendorValidationException $e) {
            throw new VendorValidationException($fileErrors + $e->errors);
        }
        if ($fileErrors !== []) {
            throw new VendorValidationException($fileErrors);
        }
        if ($this->vendors->rucExists($application->ruc->value)) {
            throw new VendorValidationException(['ruc' => 'Este RUC ya tiene una tienda en Cheppi. Escríbenos si es un error.']);
        }

        $slug = StoreSlug::unique($application->storeName, fn (string $s): bool => $this->vendors->slugExists($s));
        $vendor = $this->vendors->save(Vendor::apply(
            $userId,
            $application,
            $slug,
            $this->rules->current()->defaultPlanId,
            new DateTimeImmutable('now')
        ));

        foreach ($provided as $type) {
            $this->documents->store((int) $vendor->id, $type, $files[$type->value]);
        }

        $this->notifier->applicationReceived($vendor);
        return $vendor;
    }
}
