<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Application;

use Cheppi\Sellers\Domain\Vendor\Vendor;

interface VendorNotifier
{
    /** To the applicant and to the marketplace admin. */
    public function applicationReceived(Vendor $vendor): void;

    /** To the vendor, after an admin decision. */
    public function statusChanged(Vendor $vendor): void;

    /** To the admin, when a vendor sends missing documents. */
    public function documentsResubmitted(Vendor $vendor): void;
}
