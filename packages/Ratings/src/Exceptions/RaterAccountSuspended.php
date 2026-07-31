<?php

declare(strict_types=1);

namespace RowBuddy\Ratings\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;

/**
 * Raised when a suspended account attempts to submit a rating (ADR-026
 * §4) — checked before transfer eligibility or authorization, and
 * before any `Rating` is constructed or recorded.
 */
final class RaterAccountSuspended extends DomainException
{
    public static function forRater(string $raterId): self
    {
        return new self("Rater [{$raterId}]'s account is suspended and may not submit ratings.");
    }
}
