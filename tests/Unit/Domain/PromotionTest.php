<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Tests\Unit\Domain;

use Cheppi\Sellers\Domain\Catalog\ProductSheet;
use Cheppi\Sellers\Domain\Money\Money;
use Cheppi\Sellers\Domain\Promotion\Campaign;
use Cheppi\Sellers\Domain\Promotion\CouponDraft;
use Cheppi\Sellers\Domain\Promotion\FeaturedSpot;
use Cheppi\Sellers\Domain\Promotion\PromotionValidationException;
use Cheppi\Sellers\Tests\TestCase;
use DateTimeImmutable;
use DateTimeZone;

final class PromotionTest extends TestCase
{
    private DateTimeZone $tz;

    public function __construct()
    {
        $this->tz = new DateTimeZone('America/Lima');
    }

    public function testCampaignLowersCommissionOnlyWhileActive(): void
    {
        $cyber = Campaign::fromInput(['name' => 'Cyber Wow', 'starts_on' => '2026-11-10', 'ends_on' => '2026-11-12', 'commission' => '4,5'], $this->tz);
        $this->assertSame(450, $cyber->commissionBps);
        $this->assertSame('cyber-wow-261110', $cyber->id);
        $this->assertSame(800, Campaign::commissionFor(800, [$cyber], new DateTimeImmutable('2026-11-09 23:59', $this->tz)));
        $this->assertSame(450, Campaign::commissionFor(800, [$cyber], new DateTimeImmutable('2026-11-12 23:30', $this->tz)));
        $this->assertSame(300, Campaign::commissionFor(300, [$cyber], new DateTimeImmutable('2026-11-11', $this->tz)), 'never raises');
        $this->assertTrue($cyber->isOver(new DateTimeImmutable('2026-11-13 00:01', $this->tz)));
        $restored = Campaign::fromArray($cyber->toArray(), $this->tz);
        $this->assertSame('2026-11-12', $restored->endsOn->format('Y-m-d'));

        $e = $this->assertThrows(PromotionValidationException::class, fn () => Campaign::fromInput(['name' => 'X', 'starts_on' => '2026-11-10', 'ends_on' => '2026-11-01', 'commission' => '80'], $this->tz));
        $this->assertTrue(isset($e->errors['name'], $e->errors['ends_on'], $e->errors['commission']));
    }

    public function testCoupons(): void
    {
        $today = new DateTimeImmutable('2026-11-07', $this->tz);
        $c = CouponDraft::fromInput(['code' => ' bienvenida10 ', 'type' => 'percent', 'amount' => '10', 'expires' => '2026-12-31', 'limit' => '50'], $this->tz, $today);
        $this->assertSame('BIENVENIDA10', $c->code);
        $this->assertSame(50, $c->usageLimit);
        $e = $this->assertThrows(PromotionValidationException::class, fn () => CouponDraft::fromInput(['code' => 'a b', 'type' => 'percent', 'amount' => '95', 'expires' => '2026-01-01'], $this->tz, $today));
        $this->assertTrue(isset($e->errors['code'], $e->errors['amount'], $e->errors['expires']));
    }

    public function testFeaturedSpotPriceAndExtension(): void
    {
        $now = new DateTimeImmutable('2026-11-07 10:00');
        $spot = FeaturedSpot::quote(5, '7', Money::ofDecimal('5'), $now);
        $this->assertSame('35.00', $spot->price->toDecimal());
        $this->assertSame('2026-11-14', $spot->until->format('Y-m-d'));
        $more = FeaturedSpot::quote(5, 3, Money::ofDecimal('5'), $now, $spot->until);
        $this->assertSame('2026-11-17', $more->until->format('Y-m-d'));
        $this->assertThrows(PromotionValidationException::class, fn () => FeaturedSpot::quote(5, 45, Money::ofDecimal('5'), $now));
    }

    public function testProductSheetFromExcel(): void
    {
        $csv = "\xEF\xBB\xBFSKU;Nombre;Descripción;Categoría;Precio;Precio oferta;Stock;Imagen\n"
            . "LAB-02;Labial nude;Cremoso;Labios;29,90;;15;https://example.com/a.jpg\n"
            . ";;;;;;;\n"
            . "SER-01;Sérum;;cuidado de la piel;59.90;49.90;;\n";
        $sheet = ProductSheet::parse($csv);
        $this->assertSame([], $sheet['errors']);
        $this->assertSame(2, count($sheet['rows']));
        $this->assertSame(4, $sheet['rows'][1]['line']);
        $input = ProductSheet::toInput($sheet['rows'][0]['data'], [18 => 'Labios', 31 => 'Cuidado de la piel']);
        $this->assertSame('18', $input['category']);
        $this->assertSame('29.90', $input['price']);
        $this->assertSame('31', ProductSheet::toInput($sheet['rows'][1]['data'], [18 => 'Labios', 31 => 'Cuidado de la piel'])['category']);
        $this->assertSame('https://example.com/a.jpg', $sheet['rows'][0]['data']['image_url']);

        $bad = ProductSheet::parse("sku,descripcion\nA,b\n");
        $this->assertSame(2, count($bad['errors']));
    }
}
