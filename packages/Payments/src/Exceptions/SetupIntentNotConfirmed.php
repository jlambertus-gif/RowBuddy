<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;

/**
 * Raised when a buyer attempts to complete their payment-method setup
 * before Stripe.js has actually confirmed the SetupIntent client-side —
 * an expected client/timing condition, never treated as an infrastructure
 * failure.
 */
final class SetupIntentNotConfirmed extends DomainException
{
    public static function forSetupIntentId(string $setupIntentId): self
    {
        return new self("SetupIntent [{$setupIntentId}] has not succeeded yet.");
    }
}
