<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Tests\Unit\Domain;

use Cheppi\Sellers\Application\OrderFilter;
use Cheppi\Sellers\Domain\Order\FulfillmentStatus;
use Cheppi\Sellers\Tests\TestCase;
use DateTimeZone;

final class OrderFilterTest extends TestCase
{
    public function testReadsDatesStatusAndStore(): void
    {
        $tz = new DateTimeZone('America/Lima');
        $f = OrderFilter::fromQuery(['desde' => '2026-11-01', 'hasta' => '2026-11-07', 'estado' => 'shipped', 'tienda' => '4'], null, $tz);
        $this->assertSame(4, $f->vendorId);
        $this->assertSame(FulfillmentStatus::Shipped, $f->status);
        $this->assertSame('2026-11-01 00:00', $f->from?->format('Y-m-d H:i'));
        $this->assertSame(['estado' => 'shipped', 'desde' => '2026-11-01', 'hasta' => '2026-11-07', 'tienda' => '4'], $f->toQuery(true));
        $this->assertSame(['estado' => 'shipped', 'desde' => '2026-11-01', 'hasta' => '2026-11-07'], $f->toQuery(false));
    }

    public function testSellerCannotChooseAnotherStoreAndBadInputIsIgnored(): void
    {
        $f = OrderFilter::fromQuery(['tienda' => '9', 'estado' => 'robado', 'desde' => '07/11/2026', 'hasta' => '2026-10-01', 'x' => '1'], 2, new DateTimeZone('UTC'));
        $this->assertSame(2, $f->vendorId);
        $this->assertSame(null, $f->status);
        $this->assertSame(null, $f->from);
        $this->assertSame('2026-10-01', $f->to?->format('Y-m-d'));
    }

    public function testSwapsReversedRange(): void
    {
        $f = OrderFilter::fromQuery(['desde' => '2026-11-10', 'hasta' => '2026-11-01'], null, new DateTimeZone('UTC'));
        $this->assertSame('2026-11-01', $f->from?->format('Y-m-d'));
        $this->assertSame('2026-11-10', $f->to?->format('Y-m-d'));
        $this->assertSame([], (new OrderFilter())->toQuery(true));
    }
}
