<?php

declare(strict_types=1);

namespace RowBuddy\Ratings\Exceptions;

use RowBuddy\Ratings\ValueObjects\RatingScore;
use RowBuddy\SharedKernel\Exceptions\DomainException;

/**
 * Raised when a score outside the closed [1, 5] range reaches the domain
 * layer (ADR-024 §6) — enforced on {@see RatingScore}
 * itself, not only by presentation-layer validation, so no caller can
 * construct an out-of-range `Rating`.
 */
final class InvalidRatingScore extends DomainException
{
    public static function outOfRange(int $value): self
    {
        return new self(
            "Rating score [{$value}] is outside the allowed range of 1 to 5."
        );
    }
}
