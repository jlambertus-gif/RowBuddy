<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;

/**
 * Raised when the restricted-category or jurisdiction gate blocks a queue
 * creation attempt, for either creation path (ADR-005). `$reason` is a
 * stable machine-readable code (not the message) so callers — e.g. an HTTP
 * controller — can map it to a translated user-facing message without
 * string-matching the exception text.
 */
final class QueueSubmissionBlocked extends DomainException
{
    private function __construct(
        string $message,
        public readonly string $reason,
        public readonly string $category,
        public readonly string $jurisdictionCountry,
    ) {
        parent::__construct($message);
    }

    public static function restrictedCategory(string $category, string $jurisdictionCountry): self
    {
        return new self(
            "Category [{$category}] is restricted in jurisdiction [{$jurisdictionCountry}].",
            'restricted_category',
            $category,
            $jurisdictionCountry,
        );
    }

    public static function jurisdictionNotPermitted(string $category, string $jurisdictionCountry): self
    {
        return new self(
            "RowBuddy is not permitted to operate for category [{$category}] in jurisdiction [{$jurisdictionCountry}].",
            'jurisdiction_not_permitted',
            $category,
            $jurisdictionCountry,
        );
    }
}
