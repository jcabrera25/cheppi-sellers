<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Shipping;

/**
 * Anything that can deliver a vendor's package. Today: the vendor's own delivery.
 * Later: Cheppi Courier implements this same contract, so nothing else changes.
 */
interface ShippingProvider
{
    /** Stable code, e.g. "vendor_delivery" or "cheppi_courier". */
    public function code(): string;

    public function label(): string;

    /** Who receives the shipping money in the ledger: "vendor" or "cheppi". */
    public function payee(): string;

    /** @return ShippingQuote|null null when this provider cannot deliver to that district */
    public function quote(ShipmentRequest $request): ?ShippingQuote;
}
