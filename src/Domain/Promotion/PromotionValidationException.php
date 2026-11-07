<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Promotion;

use DomainException;

/** Field-level errors for coupons, campaigns and featured spots. */
final class PromotionValidationException extends DomainException
{
    /** @param array<string, string> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(implode(' ', $errors));
    }
}
