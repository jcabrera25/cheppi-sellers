<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Application;

use Cheppi\Sellers\Domain\Vendor\DocumentType;

/**
 * Private storage for vendor documents (never publicly reachable).
 */
interface DocumentStore
{
    /**
     * Validates and stores an uploaded file, replacing any previous file of the same type.
     *
     * @throws \Cheppi\Sellers\Domain\Vendor\VendorValidationException on bad type or size
     */
    public function store(int $vendorId, DocumentType $type, UploadedFile $file): void;

    /** @return list<array{id: int, type: DocumentType, original_name: string, mime: string, size: int, uploaded_at: string}> */
    public function forVendor(int $vendorId): array;
}
