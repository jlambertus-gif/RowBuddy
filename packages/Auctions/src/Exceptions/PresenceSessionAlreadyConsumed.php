<?php

declare(strict_types=1);

namespace RowBuddy\Auctions\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;

/**
 * Enforces the MVP restriction from ADR-009 §4 ("One PresenceSession may
 * back at most one auction in the MVP") — not a permanent domain
 * invariant. Revisit once `Position` becomes a first-class concept.
 */
final class PresenceSessionAlreadyConsumed extends DomainException
{
    public static function forPresenceSessionId(string $presenceSessionId): self
    {
        return new self(
            "Presence session [{$presenceSessionId}] already backs another auction."
        );
    }
}
