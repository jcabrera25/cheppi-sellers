<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Shipping;

use Cheppi\Sellers\Domain\Money\Money;
use Cheppi\Sellers\Domain\Shipping\LimaDistricts;
use Cheppi\Sellers\Domain\Shipping\ShipmentRequest;
use Cheppi\Sellers\Domain\Shipping\VendorDeliveryProvider;
use Cheppi\Sellers\Infrastructure\WordPress\Plugin;
use WC_Shipping_Method;

/**
 * "Delivery de la marca": quotes each vendor's package with that vendor's own zones.
 * It does not use WooCommerce shipping zones (Cheppi's own rates keep working for Cheppi's products).
 */
final class VendorDeliveryMethod extends WC_Shipping_Method
{
    public const ID = 'cheppi_vendor_delivery';

    public function __construct($instance_id = 0)
    {
        parent::__construct($instance_id);
        $this->id = self::ID;
        $this->method_title = __('Delivery de la marca (Cheppi)', 'cheppi-sellers');
        $this->method_description = __('Cada vendedor define en su panel los distritos que atiende, la tarifa y el plazo. Se aplica a los productos de ese vendedor.', 'cheppi-sellers');
        $this->title = __('Delivery de la marca', 'cheppi-sellers');
        $this->enabled = 'yes';
        $this->supports = [];
    }

    public function is_available($package): bool
    {
        return !empty($package['cheppi_vendor_id']);
    }

    public function calculate_shipping($package = []): void
    {
        $vendorId = (int) ($package['cheppi_vendor_id'] ?? 0);
        $zones = Plugin::instance()->deliveryZones()->forVendor($vendorId);
        $district = LimaDistricts::fromName((string) ($package['destination']['city'] ?? ''));
        if ($vendorId === 0 || $zones === [] || $district === null) {
            return;
        }
        $subtotal = Money::ofCents((int) round(((float) ($package['contents_cost'] ?? 0)) * 100));
        $quote = (new VendorDeliveryProvider($zones))->quote(new ShipmentRequest($vendorId, $district, $subtotal));
        if ($quote === null) {
            return;
        }
        $eta = $quote->etaDays === 0
            ? __('hoy', 'cheppi-sellers')
            : sprintf(_n('%d día', '%d días', $quote->etaDays, 'cheppi-sellers'), $quote->etaDays);
        $this->add_rate([
            'id' => self::ID . ':' . $vendorId,
            'label' => sprintf('%s · %s (%s)', __('Delivery de la marca', 'cheppi-sellers'), $quote->label, $eta),
            'cost' => $quote->cost->toDecimal(),
            'package' => $package,
            'meta_data' => ['cheppi_vendor_id' => $vendorId],
        ]);
    }
}
