<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Tests\Fakes;

use Cheppi\Sellers\Application\VendorNotifier;
use Cheppi\Sellers\Domain\Vendor\Vendor;

final class FakeNotifier implements VendorNotifier
{
    /** @var list<string> */
    public array $sent = [];

    public function applicationReceived(Vendor $vendor): void
    {
        $this->sent[] = 'received:' . $vendor->storeSlug;
    }

    public function statusChanged(Vendor $vendor): void
    {
        $this->sent[] = 'status:' . $vendor->status->value;
    }

    public function documentsResubmitted(Vendor $vendor): void
    {
        $this->sent[] = 'resubmitted:' . $vendor->storeSlug;
    }
}
