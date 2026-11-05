<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Application;

use Cheppi\Sellers\Domain\Vendor\Vendor;
use Cheppi\Sellers\Domain\Vendor\VendorStatus;

interface VendorRepository
{
    /** Inserts or updates; returns the vendor with its id. */
    public function save(Vendor $vendor): Vendor;

    public function find(int $id): ?Vendor;

    public function findByUserId(int $userId): ?Vendor;

    public function slugExists(string $slug): bool;

    public function rucExists(string $ruc): bool;

    /** @return list<Vendor> newest first */
    public function list(?VendorStatus $status, int $limit, int $offset): array;

    /** @return array<string, int> status value => count */
    public function countByStatus(): array;
}
