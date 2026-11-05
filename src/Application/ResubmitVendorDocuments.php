<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Application;

use Cheppi\Sellers\Domain\Vendor\DocumentType;
use Cheppi\Sellers\Domain\Vendor\Vendor;
use Cheppi\Sellers\Domain\Vendor\VendorStatus;
use Cheppi\Sellers\Domain\Vendor\VendorValidationException;
use DateTimeImmutable;

/**
 * Use case: a vendor whose application was observed uploads documents again; it goes back to review.
 */
final class ResubmitVendorDocuments
{
    public function __construct(
        private readonly VendorRepository $vendors,
        private readonly DocumentStore $documents,
        private readonly VendorNotifier $notifier
    ) {
    }

    /**
     * @param array<string, UploadedFile> $files keyed by DocumentType value
     * @throws VendorValidationException
     */
    public function __invoke(int $userId, array $files): Vendor
    {
        $vendor = $this->vendors->findByUserId($userId);
        if ($vendor === null || $vendor->status !== VendorStatus::NeedsInfo) {
            throw new VendorValidationException(['account' => 'Tu solicitud no está esperando documentos.']);
        }

        $valid = [];
        foreach ($files as $typeValue => $file) {
            $type = DocumentType::tryFrom((string) $typeValue);
            if ($type !== null) {
                $file->assertAcceptable('doc_' . $type->value);
                $valid[$type->value] = [$type, $file];
            }
        }
        if ($valid === []) {
            throw new VendorValidationException(['documents' => 'Sube al menos un documento.']);
        }

        foreach ($valid as [$type, $file]) {
            $this->documents->store((int) $vendor->id, $type, $file);
        }

        $vendor = $this->vendors->save($vendor->transitionTo(VendorStatus::Pending, '', new DateTimeImmutable('now')));
        $this->notifier->documentsResubmitted($vendor);
        return $vendor;
    }
}
