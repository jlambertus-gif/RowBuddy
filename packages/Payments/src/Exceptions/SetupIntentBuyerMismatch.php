<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;

/**
 * Raised when a SetupIntent id's own Stripe Customer does not carry this
 * buyer's `metadata.rowbuddy_buyer_id` — an IDOR guard against a buyer
 * submitting a SetupIntent id that never belonged to them, verified
 * server-side against Stripe itself, never against client-supplied
 * identifiers (mirroring {@see StripeConnectAccountGateway}'s own
 * `metadata.rowbuddy_seller_id` traceability convention).
 */
final class SetupIntentBuyerMismatch extends DomainException
{
    public static function forSetupIntentId(string $setupIntentId): self
    {
        return new self("SetupIntent [{$setupIntentId}] does not belong to the requesting buyer.");
    }
}
