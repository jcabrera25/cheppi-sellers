<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Front;

use Cheppi\Sellers\Application\DeliveryZones;
use Cheppi\Sellers\Application\Fulfillments;
use Cheppi\Sellers\Application\UpdateFulfillment;
use Cheppi\Sellers\Application\VendorRepository;
use Cheppi\Sellers\Domain\Order\FulfillmentStatus;
use Cheppi\Sellers\Domain\Shipping\DeliveryZone;
use Cheppi\Sellers\Domain\Shipping\ZoneValidationException;
use Cheppi\Sellers\Domain\Vendor\Vendor;
use DateTimeImmutable;
use DomainException;

/**
 * Seller panel forms for orders and delivery zones (admin-post.php).
 * After a status change the customer gets a WooCommerce note e-mail, and the order is completed
 * once every vendor delivered.
 */
final class OrdersHandler
{
    public const UPDATE_ORDER = 'cheppi_order_update';
    public const SAVE_ZONES = 'cheppi_zones_save';

    public function __construct(
        private readonly UpdateFulfillment $update,
        private readonly Fulfillments $fulfillments,
        private readonly DeliveryZones $zones,
        private readonly VendorRepository $vendors
    ) {
    }

    public function register(): void
    {
        add_action('admin_post_' . self::UPDATE_ORDER, [$this, 'updateOrder']);
        add_action('admin_post_' . self::SAVE_ZONES, [$this, 'saveZones']);
    }

    public function updateOrder(): void
    {
        $orderId = absint($_POST['order_id'] ?? 0);
        check_admin_referer(self::UPDATE_ORDER . $orderId);
        // A Cheppi admin can move any vendor's part from the management panel.
        $asAdmin = !empty($_POST['vendor_id']) && current_user_can(\Cheppi\Sellers\Infrastructure\WordPress\Capabilities::MANAGE);
        $vendor = $asAdmin ? ($this->vendors->find(absint($_POST['vendor_id'])) ?? $this->vendor()) : $this->vendor();
        $next = FulfillmentStatus::tryFrom(sanitize_key((string) ($_POST['status'] ?? '')));
        $tracking = sanitize_text_field(wp_unslash((string) ($_POST['tracking'] ?? '')));
        $before = $this->fulfillments->find($orderId, (int) $vendor->id);

        try {
            if ($next === null || $before === null) {
                throw new DomainException(__('Pedido no encontrado.', 'cheppi-sellers'));
            }
            $after = ($this->update)((int) $vendor->id, $orderId, $next, $tracking, new DateTimeImmutable('now'));
            $this->tellCustomer($orderId, $vendor, $before->status, $after->status, $after->tracking, $before->tracking);
            Flash::set(['type' => 'success', 'message' => sprintf(__('Pedido actualizado: %s.', 'cheppi-sellers'), $after->status->label())]);
        } catch (DomainException $e) {
            Flash::set(['type' => 'error', 'message' => $e->getMessage()]);
        }
        wp_safe_redirect($asAdmin ? Routes::manageOrderUrl($orderId) : Routes::orderUrl($orderId));
        exit;
    }

    public function saveZones(): void
    {
        check_admin_referer(self::SAVE_ZONES);
        $vendor = $this->vendor();
        $rows = [];
        foreach ((array) wp_unslash($_POST['zones'] ?? []) as $row) {
            $row = (array) $row;
            $rows[] = [
                'name' => sanitize_text_field((string) ($row['name'] ?? '')),
                'districts' => array_map('sanitize_key', array_map('strval', (array) ($row['districts'] ?? []))),
                'cost' => sanitize_text_field((string) ($row['cost'] ?? '')),
                'eta_days' => sanitize_text_field((string) ($row['eta_days'] ?? '')),
                'free_from' => sanitize_text_field((string) ($row['free_from'] ?? '')),
            ];
        }
        // "*" is the only non-key value; sanitize_key turns it into "".
        foreach ((array) ($_POST['zones'] ?? []) as $i => $row) {
            if (in_array('*', (array) ($row['districts'] ?? []), true)) {
                $rows[$i]['districts'][] = DeliveryZone::ANYWHERE;
            }
        }
        try {
            $this->zones->save((int) $vendor->id, DeliveryZone::listFromInput($rows));
            Flash::set(['type' => 'success', 'message' => __('Tus zonas de delivery se guardaron. Ya se aplican en el carrito.', 'cheppi-sellers')]);
        } catch (ZoneValidationException $e) {
            Flash::set(['type' => 'error', 'message' => __('Revisa las zonas marcadas.', 'cheppi-sellers'), 'errors' => $e->errors, 'values' => $rows]);
        }
        wp_safe_redirect(Routes::shippingUrl());
        exit;
    }

    private function vendor(): Vendor
    {
        $vendor = $this->vendors->findByUserId(get_current_user_id());
        if ($vendor === null || !$vendor->isPublic()) {
            wp_safe_redirect(Routes::panelUrl());
            exit;
        }
        return $vendor;
    }

    private function tellCustomer(int $orderId, Vendor $vendor, FulfillmentStatus $from, FulfillmentStatus $to, string $tracking, string $oldTracking): void
    {
        $order = wc_get_order($orderId);
        if (!$order instanceof \WC_Order || ($from === $to && $tracking === $oldTracking)) {
            return;
        }
        $message = $to->customerMessage($vendor->storeName, $tracking !== '' ? sprintf(__('Seguimiento: %s', 'cheppi-sellers'), $tracking) : '');
        if ($message !== '') {
            $order->add_order_note($message, 1); // customer note: WooCommerce e-mails it
        }
        $open = array_filter($this->fulfillments->forOrder($orderId), static fn ($f): bool => $f->status !== FulfillmentStatus::Delivered && $f->status !== FulfillmentStatus::Cancelled);
        if ($to === FulfillmentStatus::Delivered && $open === [] && $order->get_status() !== 'completed') {
            $order->update_status('completed', __('Todos los vendedores entregaron.', 'cheppi-sellers'));
        }
    }
}
