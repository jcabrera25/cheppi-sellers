<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Tests\Unit\Domain;

use Cheppi\Sellers\Application\RecordOrderSales;
use Cheppi\Sellers\Application\ReverseOrderSales;
use Cheppi\Sellers\Application\UpdateFulfillment;
use Cheppi\Sellers\Domain\Ledger\LedgerEntryType;
use Cheppi\Sellers\Domain\Money\Money;
use Cheppi\Sellers\Domain\Order\FulfillmentStatus;
use Cheppi\Sellers\Domain\Order\SaleAllocator;
use Cheppi\Sellers\Domain\Order\SaleLine;
use Cheppi\Sellers\Domain\Order\ShippingCharge;
use Cheppi\Sellers\Tests\Fakes\FakeFulfillments;
use Cheppi\Sellers\Tests\Fakes\FakeLedger;
use Cheppi\Sellers\Tests\Fakes\FakeRules;
use Cheppi\Sellers\Tests\TestCase;
use DateTimeImmutable;
use DomainException;

final class OrderTest extends TestCase
{
    /** Two vendors in one cart: vendor 1 (8%) with two lines and shipping, vendor 2 (5%) with one line. */
    private function lines(): array
    {
        return [
            new SaleLine(11, 1, 2, Money::ofDecimal('79.80'), 800),
            new SaleLine(12, 1, 1, Money::ofDecimal('129.00'), 800),
            new SaleLine(13, 2, 1, Money::ofDecimal('59.90'), 500),
        ];
    }

    public function testAllocatorSplitsSalesCommissionsAndShipping(): void
    {
        $paid = new DateTimeImmutable('2026-11-07 10:00');
        $result = SaleAllocator::allocate(500, $this->lines(), [new ShippingCharge(21, 1, Money::ofDecimal('10')), new ShippingCharge(22, 9, Money::ofDecimal('8'))], $paid);

        $sum = static function (array $entries, int $vendor, LedgerEntryType $type): string {
            $cents = 0;
            foreach ($entries as $e) {
                if ($e->vendorId === $vendor && $e->type === $type) {
                    $cents += $e->amount->cents;
                }
            }
            return Money::ofCents($cents)->toDecimal();
        };
        $this->assertSame('208.80', $sum($result['entries'], 1, LedgerEntryType::Sale));
        $this->assertSame('-16.70', $sum($result['entries'], 1, LedgerEntryType::Commission)); // 6.38 + 10.32
        $this->assertSame('10.00', $sum($result['entries'], 1, LedgerEntryType::Shipping));
        $this->assertSame('-3.00', $sum($result['entries'], 2, LedgerEntryType::Commission)); // 2.995 rounds to 3.00
        $this->assertSame('0.00', $sum($result['entries'], 9, LedgerEntryType::Shipping), 'no lines, no shipping');

        $this->assertSame(2, count($result['fulfillments']));
        $first = $result['fulfillments'][0];
        $this->assertSame(3, $first->itemsCount);
        $this->assertSame('208.80', $first->subtotal->toDecimal());
        $this->assertSame('10.00', $first->shipping->toDecimal());
        $this->assertSame(FulfillmentStatus::New, $first->status);
    }

    public function testMoneyIsHeldUntilDeliveryThenReturnWindow(): void
    {
        $ledger = new FakeLedger();
        $fulfillments = new FakeFulfillments();
        $record = new RecordOrderSales($ledger, $fulfillments);
        $paid = new DateTimeImmutable('2026-11-01 10:00');
        $this->assertTrue($record(500, $this->lines(), [new ShippingCharge(21, 1, Money::ofDecimal('10'))], $paid));
        $this->assertFalse($record(500, $this->lines(), [], $paid), 'second call is ignored');

        $now = new DateTimeImmutable('2026-11-30');
        $balance = $ledger->balance(1, $now);
        $this->assertSame('0.00', $balance->available->toDecimal());
        $this->assertSame('202.10', $balance->pending->toDecimal()); // 208.80 - 16.70 + 10

        $update = new UpdateFulfillment($fulfillments, $ledger, new FakeRules());
        $this->assertThrows(DomainException::class, fn () => $update(1, 500, FulfillmentStatus::Delivered, '', $now));
        $update(1, 500, FulfillmentStatus::Shipped, 'Motorizado Juan, 987 654 321', new DateTimeImmutable('2026-11-02'));
        $done = $update(1, 500, FulfillmentStatus::Delivered, 'Motorizado Juan, 987 654 321', new DateTimeImmutable('2026-11-03'));
        $this->assertSame(FulfillmentStatus::Delivered, $done->status);

        $returnDays = (new FakeRules())->current()->returnDays;
        $this->assertSame('0.00', $ledger->balance(1, new DateTimeImmutable('2026-11-03'))->available->toDecimal());
        $this->assertSame('202.10', $ledger->balance(1, (new DateTimeImmutable('2026-11-03'))->modify("+{$returnDays} days"))->available->toDecimal());
        $this->assertSame('56.90', $ledger->balance(2, $now)->pending->toDecimal(), 'vendor 2 still waiting');
        $this->assertThrows(DomainException::class, fn () => $update(3, 500, FulfillmentStatus::Shipped, '', $now));
    }

    public function testReverseIsIdempotentAndZeroesBalance(): void
    {
        $ledger = new FakeLedger();
        $fulfillments = new FakeFulfillments();
        (new RecordOrderSales($ledger, $fulfillments))(700, $this->lines(), [new ShippingCharge(21, 1, Money::ofDecimal('10'))], new DateTimeImmutable('2026-11-01'));
        $reverse = new ReverseOrderSales($ledger, $fulfillments);
        $this->assertTrue($reverse(700, 'Pedido cancelado', new DateTimeImmutable('2026-11-02')));
        $this->assertFalse($reverse(700, 'otra vez', new DateTimeImmutable('2026-11-02')));
        $this->assertSame('0.00', $ledger->balance(1, new DateTimeImmutable('2027-01-01'))->total()->toDecimal());
        $this->assertSame(FulfillmentStatus::Cancelled, $fulfillments->find(700, 2)->status);
        $this->assertFalse($reverse(999, 'sin ventas', new DateTimeImmutable()));
    }

    public function testFulfillmentTransitions(): void
    {
        $this->assertTrue(FulfillmentStatus::New->canMoveTo(FulfillmentStatus::Shipped));
        $this->assertFalse(FulfillmentStatus::Delivered->canMoveTo(FulfillmentStatus::Shipped));
        $this->assertFalse(FulfillmentStatus::Cancelled->isOpen());
        $this->assertSame('Marca envió tus productos. Olva 123', FulfillmentStatus::Shipped->customerMessage('Marca', 'Olva 123'));
    }
}
