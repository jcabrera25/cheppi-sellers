<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Tests\Unit\Domain;

use Cheppi\Sellers\Domain\Rules\BusinessRules;
use Cheppi\Sellers\Domain\Rules\RulesValidationException;
use Cheppi\Sellers\Infrastructure\WordPress\Admin\RulesFormMapper;
use Cheppi\Sellers\Tests\TestCase;

final class RulesFormMapperTest extends TestCase
{
    public function testFormRoundTripKeepsRules(): void
    {
        $rules = BusinessRules::defaults();
        $form = RulesFormMapper::toForm($rules);
        $back = BusinessRules::fromArray(RulesFormMapper::fromForm($form));
        $this->assertSame($rules->toArray(), $back->toArray());
    }

    public function testParsesPeopleInput(): void
    {
        $form = RulesFormMapper::toForm(BusinessRules::defaults());
        $form['default_commission_percent'] = '7,5';
        $form['plans'][] = ['id' => '', 'name' => '', 'monthly_fee' => '', 'product_limit' => '', 'commission_percent' => ''];
        $form['categories'] = [['term_id' => '15', 'commission_percent' => '10']];
        $form['payout_days'] = '31, 15,';
        $rules = BusinessRules::fromArray(RulesFormMapper::fromForm($form));
        $this->assertSame(750, $rules->defaultCommissionBps);
        $this->assertSame(2, count($rules->plans));
        $this->assertSame([15 => 1000], $rules->categoryCommissionsBps);
        $this->assertSame([15, 31], $rules->payoutDays);
    }

    public function testBadPercentReachesDomainAsFieldError(): void
    {
        $form = RulesFormMapper::toForm(BusinessRules::defaults());
        $form['default_commission_percent'] = 'ocho';
        $e = $this->assertThrows(RulesValidationException::class, fn () => BusinessRules::fromArray(RulesFormMapper::fromForm($form)));
        $this->assertTrue(isset($e->errors['default_commission_bps']));
    }
}
