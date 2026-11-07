<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Payout;

enum PayoutStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Por transferir',
            self::Paid => 'Pagado',
            self::Cancelled => 'Anulado',
        };
    }
}
