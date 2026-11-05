<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Application;

use Cheppi\Sellers\Domain\Rules\BusinessRules;
use Cheppi\Sellers\Domain\Rules\RulesValidationException;

/**
 * Use case: an admin edits the business rules.
 */
final class UpdateBusinessRules
{
    public function __construct(private readonly BusinessRulesRepository $repository)
    {
    }

    /**
     * @param array<string, mixed> $input canonical rules array
     * @throws RulesValidationException
     */
    public function __invoke(array $input, int $changedBy): BusinessRules
    {
        $rules = BusinessRules::fromArray($input);
        if ($rules->toArray() !== $this->repository->current()->toArray()) {
            $this->repository->save($rules, $changedBy);
        }
        return $rules;
    }
}
