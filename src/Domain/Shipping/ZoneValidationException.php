<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Shipping;

use DomainException;

/** Field-level errors for delivery zones. Keys look like "zones.0.cost". */
final class ZoneValidationException extends DomainException
{
    /** @param array<string, string> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(implode(' ', $errors));
    }
}
