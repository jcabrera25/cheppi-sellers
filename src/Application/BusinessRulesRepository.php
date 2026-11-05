<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Application;

use Cheppi\Sellers\Domain\Rules\BusinessRules;

/**
 * Storage port for business rules. WordPress implements it today; a future platform implements it again.
 */
interface BusinessRulesRepository
{
    public function current(): BusinessRules;

    /** Saves the rules and records who changed them and when. */
    public function save(BusinessRules $rules, int $changedBy): void;

    /**
     * @return list<array{changed_at: string, changed_by: int, rules: array<string, mixed>}>
     */
    public function history(int $limit = 20): array;
}
