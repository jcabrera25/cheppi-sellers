<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Order;

/** What the vendor has done with their part of an order. */
enum FulfillmentStatus: string
{
    case New = 'new';
    case Preparing = 'preparing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Nuevo',
            self::Preparing => 'Preparando',
            self::Shipped => 'En camino',
            self::Delivered => 'Entregado',
            self::Cancelled => 'Cancelado',
        };
    }

    /** @return list<self> */
    public function next(): array
    {
        return match ($this) {
            self::New => [self::Preparing, self::Shipped],
            self::Preparing => [self::Shipped],
            self::Shipped => [self::Delivered],
            self::Delivered, self::Cancelled => [],
        };
    }

    public function canMoveTo(self $next): bool
    {
        return in_array($next, $this->next(), true);
    }

    public function isOpen(): bool
    {
        return !in_array($this, [self::Delivered, self::Cancelled], true);
    }

    /** Message for the customer when the vendor moves the order here. */
    public function customerMessage(string $storeName, string $tracking): string
    {
        $base = match ($this) {
            self::Preparing => sprintf('%s está preparando tus productos.', $storeName),
            self::Shipped => sprintf('%s envió tus productos.', $storeName),
            self::Delivered => sprintf('%s marcó tus productos como entregados. ¡Gracias por tu compra!', $storeName),
            default => '',
        };
        return $tracking !== '' && $base !== '' ? $base . ' ' . $tracking : $base;
    }
}
