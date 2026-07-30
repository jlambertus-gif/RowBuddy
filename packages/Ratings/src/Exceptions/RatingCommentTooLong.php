<?php

declare(strict_types=1);

namespace RowBuddy\Ratings\Exceptions;

use RowBuddy\Ratings\Rating;
use RowBuddy\SharedKernel\Exceptions\DomainException;

/**
 * Raised when a non-blank comment exceeds {@see Rating::MAX_COMMENT_LENGTH}
 * (ADR-024 §6) — the bound is enforced on the aggregate itself, not only by
 * presentation-layer validation.
 */
final class RatingCommentTooLong extends DomainException
{
    public static function forLength(int $length, int $maxLength): self
    {
        return new self(
            "Rating comment length [{$length}] exceeds the maximum of [{$maxLength}] characters."
        );
    }
}
