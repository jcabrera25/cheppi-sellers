<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Promotions;

use Cheppi\Sellers\Application\BusinessRulesRepository;
use Cheppi\Sellers\Application\Ledger;
use Cheppi\Sellers\Domain\Ledger\LedgerEntry;
use Cheppi\Sellers\Domain\Ledger\LedgerEntryType;
use Cheppi\Sellers\Domain\Promotion\FeaturedSpot;
use Cheppi\Sellers\Domain\Promotion\PromotionValidationException;
use Cheppi\Sellers\Domain\Vendor\Vendor;
use DateTimeImmutable;

/**
 * Paid featured products: WooCommerce's own "featured" flag (used by the theme's featured blocks)
 * plus an end date. The fee is charged to the vendor's balance and netted at the next payout.
 */
final class FeaturedProducts
{
    public const META_UNTIL = '_cheppi_featured_until';

    public function __construct(
        private readonly Ledger $ledger,
        private readonly BusinessRulesRepository $rules
    ) {
    }

    /** @throws PromotionValidationException */
    public function buy(Vendor $vendor, int $productId, mixed $days, DateTimeImmutable $now): FeaturedSpot
    {
        $product = wc_get_product($productId);
        if (!$product || (int) get_post_field('post_author', $productId) !== $vendor->userId || $product->get_status() !== 'publish') {
            throw new PromotionValidationException(['product' => 'Elige uno de tus productos publicados.']);
        }
        $current = (int) $product->get_meta(self::META_UNTIL);
        $spot = FeaturedSpot::quote($productId, $days, $this->rules->current()->featuredPrice(), $now, $current > 0 ? new DateTimeImmutable('@' . $current) : null);

        $this->ledger->add([new LedgerEntry(
            (int) $vendor->id,
            LedgerEntryType::ServiceFee,
            $spot->price->negate(),
            $now,
            $now,
            null,
            null,
            null,
            sprintf('Producto destacado: %s (%d días)', $product->get_name(), $spot->days)
        )]);
        $product->set_featured(true);
        $product->update_meta_data(self::META_UNTIL, $spot->until->getTimestamp());
        $product->save();
        return $spot;
    }

    /** @return list<array{product: \WC_Product, until: int}> */
    public function forVendor(Vendor $vendor): array
    {
        $ids = get_posts([
            'post_type' => 'product',
            'author' => $vendor->userId,
            'numberposts' => 50,
            'fields' => 'ids',
            'meta_key' => self::META_UNTIL,
            'meta_compare' => '>',
            'meta_value' => (string) time(),
            'meta_type' => 'NUMERIC',
        ]);
        $out = [];
        foreach ($ids as $id) {
            $product = wc_get_product((int) $id);
            if ($product) {
                $out[] = ['product' => $product, 'until' => (int) $product->get_meta(self::META_UNTIL)];
            }
        }
        return $out;
    }

    /** Daily: turn off featured products whose paid period ended. */
    public function expire(): int
    {
        $ids = get_posts([
            'post_type' => 'product',
            'post_status' => 'any',
            'numberposts' => 200,
            'fields' => 'ids',
            'meta_key' => self::META_UNTIL,
            'meta_compare' => '<=',
            'meta_value' => (string) time(),
            'meta_type' => 'NUMERIC',
        ]);
        foreach ($ids as $id) {
            $product = wc_get_product((int) $id);
            if ($product) {
                $product->set_featured(false);
                $product->delete_meta_data(self::META_UNTIL);
                $product->save();
            }
        }
        return count($ids);
    }
}
