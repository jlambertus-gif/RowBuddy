<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;

/**
 * Raised when a Stripe event id has already been recorded — the
 * idempotency ledger's sole enforcement mechanism (a unique constraint on
 * stripe_event_id, translated here the same way Auctions translates its
 * own uniqueness constraint into PresenceSessionAlreadyConsumed). Callers
 * treat this as an expected, idempotent no-op, not a failure — a replayed
 * or duplicate Stripe delivery is a normal occurrence, not an error.
 */
final class WebhookEventAlreadyProcessed extends DomainException
{
    public static function forStripeEventId(string $stripeEventId): self
    {
        return new self("Stripe event [{$stripeEventId}] has already been processed.");
    }
}
