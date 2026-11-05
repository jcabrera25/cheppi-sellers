<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Tests\Unit\Domain;

use Cheppi\Sellers\Domain\Rules\BusinessRules;
use Cheppi\Sellers\Domain\Rules\Percent;
use Cheppi\Sellers\Domain\Rules\RulesValidationException;
use Cheppi\Sellers\Tests\TestCase;

final class BusinessRulesTest extends TestCase
{
    public function testDefaultsAreValidAndRoundTrip(): void
    {
        $rules = BusinessRules::defaults();
        $this->assertSame(800, $rules->defaultCommissionBps);
        $this->assertSame('emprende', $rules->defaultPlanId);
        $this->assertSame(null, $rules->plan('pro')?->productLimit);
        $this->assertSame($rules->toArray(), BusinessRules::fromArray($rules->toArray())->toArray());
    }

    public function testCollectsEveryError(): void
    {
        $data = BusinessRules::defaults()->toArray();
        $data['default_commission_bps'] = 12000;
        $data['plans'][1]['id'] = 'emprende';
        $data['payout_days'] = [0];
        $data['minimum_payout'] = '-5';
        $data['brand_page']['renewal_period'] = 'weekly';

        $e = $this->assertThrows(RulesValidationException::class, fn () => BusinessRules::fromArray($data));
        $this->assertTrue(isset($e->errors['default_commission_bps']));
        $this->assertTrue(isset($e->errors['plans.1.id']));
        $this->assertTrue(isset($e->errors['payout_days']));
        $this->assertTrue(isset($e->errors['minimum_payout']));
        $this->assertTrue(isset($e->errors['brand_page.renewal_period']));
    }

    public function testDefaultPlanMustExist(): void
    {
        $data = BusinessRules::defaults()->toArray();
        $data['default_plan_id'] = 'oro';
        $e = $this->assertThrows(RulesValidationException::class, fn () => BusinessRules::fromArray($data));
        $this->assertTrue(isset($e->errors['default_plan_id']));
    }

    public function testPayoutDaysAreSortedAndUnique(): void
    {
        $data = BusinessRules::defaults()->toArray();
        $data['payout_days'] = ['31', 15, 15];
        $this->assertSame([15, 31], BusinessRules::fromArray($data)->payoutDays);
    }

    public function testPercentConversion(): void
    {
        $this->assertSame(850, Percent::toBps('8,5'));
        $this->assertSame('8.5', Percent::fromBps(850));
        $this->assertSame('12', Percent::fromBps(1200));
    }
}
