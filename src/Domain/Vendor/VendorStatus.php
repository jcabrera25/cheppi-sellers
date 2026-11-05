<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Vendor;

enum VendorStatus: string
{
    case Pending = 'pending';           // En revisión
    case NeedsInfo = 'needs_info';      // Falta un documento
    case Approved = 'approved';         // Aprobada
    case Rejected = 'rejected';         // Rechazada
    case Suspended = 'suspended';       // Suspendida

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En revisión',
            self::NeedsInfo => 'Falta un documento',
            self::Approved => 'Aprobada',
            self::Rejected => 'Rechazada',
            self::Suspended => 'Suspendida',
        };
    }

    public function canSell(): bool
    {
        return $this === self::Approved;
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Approved, self::NeedsInfo, self::Rejected],
            self::NeedsInfo => [self::Pending, self::Rejected],
            self::Approved => [self::Suspended],
            self::Suspended => [self::Approved, self::Rejected],
            self::Rejected => [self::Pending],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }
}
