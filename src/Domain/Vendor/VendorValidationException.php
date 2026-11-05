<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Vendor;

use DomainException;

/**
 * Field-level errors for vendor input. Keys match form field names.
 */
final class VendorValidationException extends DomainException
{
    /** @param array<string, string> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(implode(' ', $errors));
    }
}
