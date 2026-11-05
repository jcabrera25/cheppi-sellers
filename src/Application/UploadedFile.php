<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Application;

use Cheppi\Sellers\Domain\Vendor\DocumentType;
use Cheppi\Sellers\Domain\Vendor\VendorValidationException;

/**
 * A file received from a form, with its real MIME type detected from content (not the browser's claim).
 */
final class UploadedFile
{
    public function __construct(
        public readonly string $tmpPath,
        public readonly string $originalName,
        public readonly string $mime,
        public readonly int $size
    ) {
    }

    /** @throws VendorValidationException */
    public function assertAcceptable(string $field): void
    {
        if (!isset(DocumentType::ALLOWED_MIME[$this->mime])) {
            throw new VendorValidationException([$field => 'Sube un PDF, JPG, PNG o WebP.']);
        }
        if ($this->size <= 0 || $this->size > DocumentType::MAX_BYTES) {
            throw new VendorValidationException([$field => 'El archivo debe pesar menos de 5 MB.']);
        }
    }

    public function extension(): string
    {
        return DocumentType::ALLOWED_MIME[$this->mime] ?? 'bin';
    }
}
