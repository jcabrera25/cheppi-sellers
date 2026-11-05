<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Tests\Fakes;

use Cheppi\Sellers\Application\BusinessRulesRepository;
use Cheppi\Sellers\Domain\Rules\BusinessRules;

final class FakeRules implements BusinessRulesRepository
{
    public function current(): BusinessRules
    {
        return BusinessRules::defaults();
    }

    public function save(BusinessRules $rules, int $changedBy): void
    {
    }

    public function history(int $limit = 20): array
    {
        return [];
    }
}
