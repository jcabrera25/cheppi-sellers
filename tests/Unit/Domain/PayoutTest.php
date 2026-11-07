<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Tests\Unit\Domain;

use Cheppi\Sellers\Application\GeneratePayouts;
use Cheppi\Sellers\Application\RecordOrderSales;
use Cheppi\Sellers\Application\SettlePayout;
use Cheppi\Sellers\Application\UpdateFulfillment;
use Cheppi\Sellers\Domain\Ledger\LedgerEntry;
use Cheppi\Sellers\Domain\Ledger\LedgerEntryType;
use Cheppi\Sellers\Domain\Money\Money;
use Cheppi\Sellers\Domain\Order\FulfillmentStatus;
use Cheppi\Sellers\Domain\Order\SaleLine;
use Cheppi\Sellers\Domain\Payout\BankDetails;
use Cheppi\Sellers\Domain\Payout\PayoutStatus;
use Cheppi\Sellers\Domain\Vendor\VendorValidationException;
use Cheppi\Sellers\Tests\Fakes\FakeFulfillments;
use Cheppi\Sellers\Tests\Fakes\FakeLedger;
use Cheppi\Sellers\Tests\Fakes\FakePayouts;
use Cheppi\Sellers\Tests\Fakes\FakeRules;
use Cheppi\Sellers\Tests\TestCase;
use DateTimeImmutable;
use DomainException;

final class PayoutTest extends TestCase
{
    /** Vendor 1 sells S/100 (8%) on Nov 1, delivers Nov 2; return window 7 days → payable from Nov 9. */
    private function scenario(): array
    {
        $ledger = new FakeLedger();
        $fulfillments = new FakeFulfillments();
        (new RecordOrderSales($ledger, $fulfillments))(10, [new SaleLine(1, 1, 1, Money::ofDecimal('100'), 800)], [], new DateTimeImmutable('2026-11-01'));
        (new RecordOrderSales($ledger, $fulfillments))(11, [new SaleLine(2, 1, 1, Money::ofDecimal('50'), 800)], [], new DateTimeImmutable('2026-11-01'));
        $update = new UpdateFulfillment($fulfillments, $ledger, new FakeRules());
        $update(1, 10, FulfillmentStatus::Shipped, '', new DateTimeImmutable('2026-11-02'));
        $update(1, 10, FulfillmentStatus::Delivered, '', new DateTimeImmutable('2026-11-02'));
        return [$ledger, new FakePayouts()];
    }

    public function testCutoffPaysOnlyReleasedMoneyOnce(): void
    {
        [$ledger, $payouts] = $this->scenario();
        $generate = new GeneratePayouts($ledger, $payouts, new FakeRules());

        $this->assertSame(0, count($generate(new DateTimeImmutable('2026-11-08'), new DateTimeImmutable('2026-11-08'))), 'still in return window');
        $created = $generate(new DateTimeImmutable('2026-11-15'), new DateTimeImmutable('2026-11-15 06:00'));
        $this->assertSame(1, count($created));
        $this->assertSame('92.00', $created[0]->amount->toDecimal(), 'order 11 not delivered yet');
        $this->assertSame(0, count($generate(new DateTimeImmutable('2026-11-15'), new DateTimeImmutable('2026-11-15 07:00'))), 'idempotent');

        $balance = $ledger->balance(1, new DateTimeImmutable('2026-11-15 08:00'));
        $this->assertSame('0.00', $balance->available->toDecimal());
        $this->assertSame('46.00', $balance->pending->toDecimal());
        $this->assertSame(2, count(array_filter($ledger->forPayout((int) $created[0]->id), static fn (LedgerEntry $e): bool => $e->type !== LedgerEntryType::Payout)));
    }

    public function testMinimumAndFeesAndPayAndCancel(): void
    {
        [$ledger, $payouts] = $this->scenario();
        // A featured-product fee is charged right away and is netted in the next payout.
        $ledger->add([new LedgerEntry(1, LedgerEntryType::ServiceFee, Money::ofDecimal('-10'), new DateTimeImmutable('2026-11-03'), new DateTimeImmutable('2026-11-03'))]);
        $generate = new GeneratePayouts($ledger, $payouts, new FakeRules());
        $payout = $generate(new DateTimeImmutable('2026-11-15'), new DateTimeImmutable('2026-11-15'))[0];
        $this->assertSame('82.00', $payout->amount->toDecimal());

        $settle = new SettlePayout($ledger, $payouts);
        $this->assertThrows(DomainException::class, fn () => $settle->pay((int) $payout->id, '  ', new DateTimeImmutable()));
        $cancelled = $settle->cancel((int) $payout->id, new DateTimeImmutable('2026-11-16'));
        $this->assertSame(PayoutStatus::Cancelled, $cancelled->status);
        $this->assertSame('82.00', $ledger->balance(1, new DateTimeImmutable('2026-11-16'))->available->toDecimal(), 'money is back');

        $again = $generate(new DateTimeImmutable('2026-11-30'), new DateTimeImmutable('2026-11-30'))[0];
        $this->assertSame('82.00', $again->amount->toDecimal(), 'nothing counted twice');
        $paid = $settle->pay((int) $again->id, 'OP-123456', new DateTimeImmutable('2026-12-01'));
        $this->assertSame(PayoutStatus::Paid, $paid->status);
        $this->assertThrows(DomainException::class, fn () => $settle->cancel((int) $again->id, new DateTimeImmutable()));
    }

    public function testBankDetails(): void
    {
        $ok = BankDetails::fromInput(['bank' => 'bcp', 'holder' => 'Prueba Cheppi SAC', 'account' => '191-12345678-0-12', 'cci' => '002 191 112345678012 34']);
        $this->assertSame('00219111234567801234', $ok->cci);
        $this->assertSame('••••••••••••••••1234', $ok->maskedCci());
        $e = $this->assertThrows(VendorValidationException::class, fn () => BankDetails::fromInput(['bank' => 'x', 'holder' => 'A', 'cci' => '123']));
        $this->assertTrue(isset($e->errors['bank'], $e->errors['holder'], $e->errors['cci']));
    }
}
