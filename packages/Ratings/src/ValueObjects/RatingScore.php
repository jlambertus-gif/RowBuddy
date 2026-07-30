<?php

declare(strict_types=1);

namespace RowBuddy\Ratings\ValueObjects;

use RowBuddy\Ratings\Exceptions\InvalidRatingScore;

/**
 * A single required integer score in the closed range [1, 5] (ADR-024
 * §6) — no sub-dimensional or category-specific scores exist. Validated
 * here, at construction, so an out-of-range score can never reach
 * persistence through any caller, satisfying ADR-024 §6's explicit
 * requirement that the range be enforced by the domain model itself.
 */
final class RatingScore
{
    public const MIN = 1;

    public const MAX = 5;

    public readonly int $value;

    public function __construct(int $value)
    {
        if ($value < self::MIN || $value > self::MAX) {
            throw InvalidRatingScore::outOfRange($value);
        }

        $this->value = $value;
    }
}
