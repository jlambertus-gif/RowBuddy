<?php

declare(strict_types=1);

namespace RowBuddy\SharedKernel\ValueObjects;

use RowBuddy\SharedKernel\Exceptions\ValidationException;

/**
 * An immutable monetary amount stored in minor units (cents) to avoid
 * floating-point error, paired with its currency. Every module that deals
 * with prices, bids, fees, or payouts should pass this type across module
 * boundaries instead of raw integers/floats plus a currency string.
 */
final class Money
{
    public readonly int $minorUnits;

    public readonly Currency $currency;

    public function __construct(int $minorUnits, Currency $currency)
    {
        if ($minorUnits < 0) {
            throw new ValidationException(
                "Money cannot be negative, got [{$minorUnits}] minor units."
            );
        }

        $this->minorUnits = $minorUnits;
        $this->currency = $currency;
    }

    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits + $other->minorUnits, $this->currency);
    }

    public function subtract(Money $other): self
    {
        $this->assertSameCurrency($other);

        if ($other->minorUnits > $this->minorUnits) {
            throw new ValidationException('Cannot subtract a larger Money amount, result would be negative.');
        }

        return new self($this->minorUnits - $other->minorUnits, $this->currency);
    }

    /**
     * @param  int  $percentage  whole-number basis, e.g. 10 for 10%
     */
    public function percentage(int $percentage): self
    {
        $amount = (int) round($this->minorUnits * $percentage / 100);

        return new self($amount, $this->currency);
    }

    public function equals(Money $other): bool
    {
        return $this->minorUnits === $other->minorUnits
            && $this->currency->equals($other->currency);
    }

    public function isGreaterThan(Money $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->minorUnits > $other->minorUnits;
    }

    public function isZero(): bool
    {
        return $this->minorUnits === 0;
    }

    private function assertSameCurrency(Money $other): void
    {
        if (! $this->currency->equals($other->currency)) {
            throw new ValidationException(
                "Cannot operate on Money in different currencies: [{$this->currency}] vs [{$other->currency}]."
            );
        }
    }
}
