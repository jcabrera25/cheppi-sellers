<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Money;

use InvalidArgumentException;

/**
 * Immutable amount of money stored in minor units (céntimos) to avoid float errors.
 */
final class Money
{
    private function __construct(
        public readonly int $cents,
        public readonly string $currency
    ) {
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('Currency must be an ISO 4217 code.');
        }
    }

    public static function ofCents(int $cents, string $currency = 'PEN'): self
    {
        return new self($cents, $currency);
    }

    /** Builds from a decimal string or number such as "19.90". Rounds half up. */
    public static function ofDecimal(string|int|float $amount, string $currency = 'PEN'): self
    {
        $value = is_string($amount) ? trim($amount) : (string) $amount;
        if (!is_numeric($value)) {
            throw new InvalidArgumentException('Amount is not numeric.');
        }
        return new self((int) round(((float) $value) * 100), $currency);
    }

    public static function zero(string $currency = 'PEN'): self
    {
        return new self(0, $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->cents + $other->cents, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->cents - $other->cents, $this->currency);
    }

    /** Applies a rate in basis points (800 = 8%). Rounds half up. */
    public function percentage(int $basisPoints): self
    {
        if ($basisPoints < 0) {
            throw new InvalidArgumentException('Rate cannot be negative.');
        }
        $abs = intdiv(abs($this->cents) * $basisPoints + 5000, 10000);
        return new self($this->cents < 0 ? -$abs : $abs, $this->currency);
    }

    public function negate(): self
    {
        return new self(-$this->cents, $this->currency);
    }

    public function isNegative(): bool
    {
        return $this->cents < 0;
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents && $this->currency === $other->currency;
    }

    public function toDecimal(): string
    {
        $sign = $this->cents < 0 ? '-' : '';
        $abs = abs($this->cents);
        return sprintf('%s%d.%02d', $sign, intdiv($abs, 100), $abs % 100);
    }

    private function assertSameCurrency(self $other): void
    {
        if ($other->currency !== $this->currency) {
            throw new InvalidArgumentException('Currency mismatch.');
        }
    }
}
