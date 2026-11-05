<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Ledger;

/**
 * Kinds of movement in a vendor's account. Sign convention: positive = owed to the vendor.
 */
enum LedgerEntryType: string
{
    case Sale = 'sale';               // + line total sold by the vendor
    case Commission = 'commission';   // - Cheppi's commission on a sale
    case Shipping = 'shipping';       // + shipping charged to the customer, when the vendor delivers
    case Refund = 'refund';           // - money returned to a customer
    case ServiceFee = 'service_fee';  // - paid extras charged to the vendor (premium brand page, featured spots)
    case Payout = 'payout';           // - money transferred to the vendor
    case Adjustment = 'adjustment';   // +/- manual correction by an admin

    public function label(): string
    {
        return match ($this) {
            self::Sale => 'Venta',
            self::Commission => 'Comisión Cheppi',
            self::Shipping => 'Envío',
            self::Refund => 'Devolución',
            self::ServiceFee => 'Servicio adicional',
            self::Payout => 'Liquidación',
            self::Adjustment => 'Ajuste',
        };
    }
}
