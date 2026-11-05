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

    /** @param int|null $exceptId ignore this vendor (when editing it) */
    public function slugExists(string $slug, ?int $exceptId = null): bool;

    /** Active RUC (rejected vendors do not block it). */
    public function rucExists(string $ruc, ?int $exceptId = null): bool;

    /**
     * @param string $search matches store name, legal name or RUC
     * @return list<Vendor> newest first
     */
    public function list(?VendorStatus $status, int $limit, int $offset, string $search = ''): array;

    /** @return array<string, int> status value => count */
    public function countByStatus(): array;

    public function delete(int $id): void;
}
