<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Order;

use Cheppi\Sellers\Domain\Commission\CommissionResolver;
use Cheppi\Sellers\Domain\Commission\CommissionSnapshot;
use Cheppi\Sellers\Domain\Ledger\LedgerEntry;
use Cheppi\Sellers\Domain\Ledger\LedgerEntryType;
use Cheppi\Sellers\Domain\Money\Money;
use DateTimeImmutable;

/**
 * Turns a paid order into ledger entries and one fulfillment per vendor.
 * Money stays on hold (available_at far in the future) until the vendor marks the delivery.
 */
final class SaleAllocator
{
    /**
     * @param list<SaleLine>       $lines
     * @param list<ShippingCharge> $shipping
     * @return array{entries: list<LedgerEntry>, fulfillments: list<Fulfillment>}
     */
    public static function allocate(int $orderId, array $lines, array $shipping, DateTimeImmutable $paidAt, string $currency = 'PEN'): array
    {
        $hold = LedgerEntry::untilDelivered();
        $entries = [];
        $perVendor = [];

        foreach ($lines as $line) {
            $entries[] = new LedgerEntry($line->vendorId, LedgerEntryType::Sale, $line->total, $paidAt, $hold, $orderId, $line->orderItemId, $line->commissionBps);
            $commission = CommissionResolver::commissionFor($line->total, new CommissionSnapshot($line->commissionBps, CommissionSnapshot::SOURCE_VENDOR));
            if ($commission->cents > 0) {
                $entries[] = new LedgerEntry($line->vendorId, LedgerEntryType::Commission, $commission->negate(), $paidAt, $hold, $orderId, $line->orderItemId, $line->commissionBps);
            }
            $perVendor[$line->vendorId] ??= ['items' => 0, 'subtotal' => Money::zero($currency), 'shipping' => Money::zero($currency)];
            $perVendor[$line->vendorId]['items'] += $line->quantity;
            $perVendor[$line->vendorId]['subtotal'] = $perVendor[$line->vendorId]['subtotal']->add($line->total);
        }

        foreach ($shipping as $charge) {
            if (!isset($perVendor[$charge->vendorId]) || $charge->amount->cents <= 0) {
                continue; // shipping for a vendor with no lines in this order is not theirs to collect
            }
            $entries[] = new LedgerEntry($charge->vendorId, LedgerEntryType::Shipping, $charge->amount, $paidAt, $hold, $orderId, $charge->orderItemId);
            $perVendor[$charge->vendorId]['shipping'] = $perVendor[$charge->vendorId]['shipping']->add($charge->amount);
        }

        $fulfillments = [];
        foreach ($perVendor as $vendorId => $sum) {
            $fulfillments[] = new Fulfillment($orderId, $vendorId, FulfillmentStatus::New, $sum['items'], $sum['subtotal'], $sum['shipping'], '', $paidAt, $paidAt);
        }
        return ['entries' => $entries, 'fulfillments' => $fulfillments];
    }
}
