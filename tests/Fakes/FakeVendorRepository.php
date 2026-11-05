<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Tests\Fakes;

use Cheppi\Sellers\Application\VendorRepository;
use Cheppi\Sellers\Domain\Vendor\Vendor;
use Cheppi\Sellers\Domain\Vendor\VendorStatus;

final class FakeVendorRepository implements VendorRepository
{
    /** @var array<int, Vendor> */
    public array $vendors = [];
    private int $next = 1;

    public function save(Vendor $vendor): Vendor
    {
        $vendor = $vendor->id === null ? $vendor->withId($this->next++) : $vendor;
        $this->vendors[(int) $vendor->id] = $vendor;
        return $vendor;
    }

    public function find(int $id): ?Vendor
    {
        return $this->vendors[$id] ?? null;
    }

    public function findByUserId(int $userId): ?Vendor
    {
        foreach ($this->vendors as $v) {
            if ($v->userId === $userId) {
                return $v;
            }
        }
        return null;
    }

    public function slugExists(string $slug): bool
    {
        foreach ($this->vendors as $v) {
            if ($v->storeSlug === $slug) {
                return true;
            }
        }
        return false;
    }

    public function rucExists(string $ruc): bool
    {
        foreach ($this->vendors as $v) {
            if ($v->ruc === $ruc) {
                return true;
            }
        }
        return false;
    }

    public function list(?VendorStatus $status, int $limit, int $offset): array
    {
        return array_values($this->vendors);
    }

    public function countByStatus(): array
    {
        return [];
    }
}
