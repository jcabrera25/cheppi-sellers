<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Rules;

use DomainException;

/**
 * Thrown when business rules input is invalid. Carries one message per field.
 */
final class RulesValidationException extends DomainException
{
    /** @param array<string, string> $errors field => message */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Las reglas de negocio tienen errores: ' . implode(' ', $errors));
    }
}
