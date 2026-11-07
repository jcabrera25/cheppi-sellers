<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Shipping;

use Cheppi\Sellers\Application\DeliveryZones;
use Cheppi\Sellers\Domain\Shipping\LimaDistricts;
use Cheppi\Sellers\Infrastructure\WordPress\Persistence\WpVendorRepository;

/**
 * Splits the cart into one shipping package per vendor (plus one for Cheppi's own products),
 * so each brand quotes and delivers its part. Also turns the checkout "city" into a district list.
 */
final class CartShipping
{
    public function __construct(
        private readonly WpVendorRepository $vendors,
        private readonly DeliveryZones $zones
    ) {
    }

    public function register(): void
    {
        add_filter('woocommerce_shipping_methods', static function (array $methods): array {
            $methods[VendorDeliveryMethod::ID] = VendorDeliveryMethod::class;
            return $methods;
        });
        add_filter('woocommerce_cart_shipping_packages', [$this, 'split']);
        add_filter('woocommerce_package_rates', [$this, 'filterRates'], 20, 2);
        add_filter('woocommerce_shipping_package_name', [$this, 'packageName'], 10, 3);
        add_filter('woocommerce_default_address_fields', [$this, 'districtField']);
    }

    /**
     * @param list<array<string, mixed>> $packages
     * @return list<array<string, mixed>>
     */
    public function split(array $packages): array
    {
        $base = $packages[0] ?? null;
        if ($base === null || empty($base['contents'])) {
            return $packages;
        }
        $owners = [];
        foreach ($base['contents'] as $item) {
            $owners[] = (int) get_post_field('post_author', (int) ($item['product_id'] ?? 0));
        }
        $vendors = $this->vendors->findByUserIds($owners);

        $groups = [];
        foreach ($base['contents'] as $key => $item) {
            $owner = (int) get_post_field('post_author', (int) ($item['product_id'] ?? 0));
            $vendor = $vendors[$owner] ?? null;
            $groupKey = $vendor !== null && $vendor->isPublic() ? (int) $vendor->id : 0;
            $groups[$groupKey]['contents'][$key] = $item;
            $groups[$groupKey]['vendor'] = $vendor !== null && $vendor->isPublic() ? $vendor : null;
        }
        if (count($groups) === 1 && isset($groups[0])) {
            return $packages; // only Cheppi's own products: WooCommerce as usual
        }

        $out = [];
        foreach ($groups as $vendorId => $group) {
            $package = $base;
            $package['contents'] = $group['contents'];
            $package['contents_cost'] = array_sum(array_map(static fn (array $i): float => (float) ($i['line_total'] ?? 0), $group['contents']));
            $package['cheppi_vendor_id'] = $vendorId;
            $package['cheppi_store_name'] = $group['vendor']?->storeName ?? '';
            $out[] = $package;
        }
        return $out;
    }

    /**
     * A vendor with zones delivers their package themselves (only their rates). A vendor without zones yet
     * falls back to Cheppi's normal rates. Cheppi's package never shows vendor rates.
     *
     * @param array<string, \WC_Shipping_Rate> $rates
     * @param array<string, mixed>             $package
     */
    public function filterRates(array $rates, array $package): array
    {
        $vendorId = (int) ($package['cheppi_vendor_id'] ?? 0);
        $own = static fn (\WC_Shipping_Rate $r): bool => $r->get_method_id() === VendorDeliveryMethod::ID;
        if ($vendorId > 0 && $this->zones->forVendor($vendorId) !== []) {
            return array_filter($rates, $own);
        }
        return array_filter($rates, static fn (\WC_Shipping_Rate $r): bool => !$own($r));
    }

    /** @param array<string, mixed> $package */
    public function packageName(string $name, int|string $index, array $package): string
    {
        $store = (string) ($package['cheppi_store_name'] ?? '');
        if (!empty($package['cheppi_vendor_id']) && $store !== '') {
            return sprintf(__('Envío de %s', 'cheppi-sellers'), $store);
        }
        return isset($package['cheppi_vendor_id']) ? __('Envío de Cheppi', 'cheppi-sellers') : $name;
    }

    /** Lima and Callao districts as a list, so every vendor can match the address to their zones. */
    public function districtField(array $fields): array
    {
        if (!isset($fields['city'])) {
            return $fields;
        }
        $options = ['' => __('Elige tu distrito', 'cheppi-sellers')];
        foreach (LimaDistricts::ALL as $label) {
            $options[$label] = $label;
        }
        $fields['city']['label'] = __('Distrito', 'cheppi-sellers');
        $fields['city']['type'] = 'select';
        $fields['city']['options'] = $options;
        $fields['city']['input_class'] = ['wc-enhanced-select'];
        return $fields;
    }
}
