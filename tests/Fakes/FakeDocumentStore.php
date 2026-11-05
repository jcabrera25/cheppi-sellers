<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Tests\Fakes;

use Cheppi\Sellers\Application\DocumentStore;
use Cheppi\Sellers\Application\UploadedFile;
use Cheppi\Sellers\Domain\Vendor\DocumentType;

final class FakeDocumentStore implements DocumentStore
{
    /** @var array<int, array<string, UploadedFile>> */
    public array $docs = [];

    public function store(int $vendorId, DocumentType $type, UploadedFile $file): void
    {
        $this->docs[$vendorId][$type->value] = $file;
    }

    public function forVendor(int $vendorId): array
    {
        return [];
    }

    public function delete(int $vendorId, DocumentType $type): void
    {
        unset($this->docs[$vendorId][$type->value]);
    }

    public function deleteAllFor(int $vendorId): void
    {
        unset($this->docs[$vendorId]);
    }
}
