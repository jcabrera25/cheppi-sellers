<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Catalog;

use DomainException;

/** Field-level errors for product input. Keys match form field names. */
final class ProductValidationException extends DomainException
{
    /** @param array<string, string> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(implode(' ', $errors));
    }
}
