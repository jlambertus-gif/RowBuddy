<?php

declare(strict_types=1);

namespace RowBuddy\Queues\ValueObjects;

use DateTimeImmutable;

/**
 * A single versioned, effective-dated legal-gating rule for a country
 * (optionally scoped to one category), per
 * docs/legal/jurisdiction-requirements.md: "a queue's country/category
 * determines whether the platform can legally operate there, and that
 * determination can change over time". A null `$category` means the rule
 * applies to every category in that country.
 *
 * Callers are responsible for not creating overlapping effective windows
 * for the same country+category — this value object and the gate that
 * consumes it assume at most one rule is effective at a given instant.
 */
final class JurisdictionRule
{
    public function __construct(
        public readonly string $jurisdictionCountry,
        public readonly ?string $category,
        public readonly bool $permitted,
        public readonly DateTimeImmutable $effectiveFrom,
        public readonly ?DateTimeImmutable $effectiveTo,
    ) {}

    public function isEffectiveAt(DateTimeImmutable $when): bool
    {
        if ($when < $this->effectiveFrom) {
            return false;
        }

        return $this->effectiveTo === null || $when < $this->effectiveTo;
    }

    public function appliesToCategory(string $category): bool
    {
        return $this->category === null || $this->category === $category;
    }
}
