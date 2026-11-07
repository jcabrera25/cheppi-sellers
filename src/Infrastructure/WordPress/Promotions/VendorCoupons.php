<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Promotions;

use Cheppi\Sellers\Domain\Promotion\CouponDraft;
use Cheppi\Sellers\Domain\Promotion\PromotionValidationException;
use Cheppi\Sellers\Domain\Vendor\Vendor;
use Cheppi\Sellers\Infrastructure\WordPress\Persistence\WpVendorRepository;
use WC_Coupon;
use WC_Product;

/**
 * Vendor coupons are normal WooCommerce coupons tagged with the vendor. They only discount that
 * vendor's products, so the discount comes out of that vendor's sale (commission is on the paid price).
 */
final class VendorCoupons
{
    public const META = '_cheppi_vendor_id';

    public function __construct(private readonly WpVendorRepository $vendors)
    {
    }

    public function register(): void
    {
        add_filter('woocommerce_coupon_is_valid_for_product', [$this, 'validForProduct'], 10, 3);
        add_filter('woocommerce_coupon_is_valid', [$this, 'validForCart'], 10, 2);
    }

    /** @throws PromotionValidationException */
    public function create(Vendor $vendor, CouponDraft $draft): int
    {
        if (wc_get_coupon_id_by_code($draft->code) > 0) {
            throw new PromotionValidationException(['code' => 'Ese código ya existe. Prueba con otro.']);
        }
        $coupon = new WC_Coupon();
        $coupon->set_code($draft->code);
        $coupon->set_discount_type($draft->type);
        $coupon->set_amount($draft->amount);
        $coupon->set_individual_use(false);
        $coupon->set_description(sprintf('Cupón de %s', $vendor->storeName));
        if ($draft->minimumSpend !== null) {
            $coupon->set_minimum_amount($draft->minimumSpend);
        }
        if ($draft->expiresOn !== null) {
            $coupon->set_date_expires($draft->expiresOn->setTime(23, 59, 59)->getTimestamp());
        }
        if ($draft->usageLimit !== null) {
            $coupon->set_usage_limit($draft->usageLimit);
        }
        $coupon->update_meta_data(self::META, (int) $vendor->id);
        $id = $coupon->save();
        wp_update_post(['ID' => $id, 'post_author' => $vendor->userId]);
        return $id;
    }

    /** @return list<WC_Coupon> */
    public function forVendor(Vendor $vendor): array
    {
        $ids = get_posts([
            'post_type' => 'shop_coupon',
            'post_status' => 'publish',
            'numberposts' => 50,
            'fields' => 'ids',
            'meta_key' => self::META,
            'meta_value' => (string) $vendor->id,
        ]);
        return array_map(static fn (int $id): WC_Coupon => new WC_Coupon($id), array_map('intval', $ids));
    }

    public function delete(Vendor $vendor, int $couponId): bool
    {
        $coupon = new WC_Coupon($couponId);
        if ((int) $coupon->get_meta(self::META) !== (int) $vendor->id) {
            return false;
        }
        wp_trash_post($couponId);
        return true;
    }

    /** @param WC_Product $product */
    public function validForProduct(bool $valid, $product, WC_Coupon $coupon): bool
    {
        $vendorId = (int) $coupon->get_meta(self::META);
        if ($vendorId === 0 || !$product instanceof WC_Product) {
            return $valid;
        }
        return $valid && $this->ownerVendorId($product) === $vendorId;
    }

    public function validForCart(bool $valid, WC_Coupon $coupon): bool
    {
        $vendorId = (int) $coupon->get_meta(self::META);
        if (!$valid || $vendorId === 0 || !function_exists('WC') || WC()->cart === null) {
            return $valid;
        }
        foreach (WC()->cart->get_cart() as $item) {
            if (($item['data'] ?? null) instanceof WC_Product && $this->ownerVendorId($item['data']) === $vendorId) {
                return true;
            }
        }
        $vendor = $this->vendors->find($vendorId);
        throw new \Exception(esc_html(sprintf(__('Este cupón es de %s y solo aplica a sus productos.', 'cheppi-sellers'), $vendor?->storeName ?? __('otra tienda', 'cheppi-sellers'))));
    }

    private function ownerVendorId(WC_Product $product): int
    {
        $owner = (int) get_post_field('post_author', $product->get_parent_id() ?: $product->get_id());
        $vendor = $this->vendors->findByUserIds([$owner])[$owner] ?? null;
        return (int) ($vendor?->id ?? 0);
    }
}
