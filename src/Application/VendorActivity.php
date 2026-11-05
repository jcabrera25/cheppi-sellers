<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Application;

/**
 * Read-only view of what a vendor has done that must not be erased (sales, payouts).
 */
interface VendorActivity
{
    public function hasFinancialHistory(int $vendorId): bool;
}
