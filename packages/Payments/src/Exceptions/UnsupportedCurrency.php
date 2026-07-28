<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;
use RowBuddy\SharedKernel\ValueObjects\Currency;

/**
 * Raised when a `PaymentIntent`'s amount is denominated in a different
 * currency than the currently-configured {@see
 * \RowBuddy\Payments\Contracts\TransactionValueLimitPolicy} maximum — the
 * MVP's USD-only policy (currency governance is otherwise unenforced
 * anywhere upstream in Auctions/Bids) enforced as an aggregate invariant,
 * regardless of which caller constructs the `PaymentIntent`.
 */
final class UnsupportedCurrency extends DomainException
{
    public static function forPaymentIntent(string $paymentIntentId, Currency $given, Currency $expected): self
    {
        return new self(
            "PaymentIntent [{$paymentIntentId}] amount currency [{$given}] is not supported; expected [{$expected}]."
        );
    }
}
