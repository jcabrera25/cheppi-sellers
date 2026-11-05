<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Tests\Fakes;

use Cheppi\Sellers\Application\VendorActivity;

final class FakeActivity implements VendorActivity
{
    /** @param list<int> $withHistory */
    public function __construct(public array $withHistory = [])
    {
    }

    public function hasFinancialHistory(int $vendorId): bool
    {
        return in_array($vendorId, $this->withHistory, true);
    }
}
