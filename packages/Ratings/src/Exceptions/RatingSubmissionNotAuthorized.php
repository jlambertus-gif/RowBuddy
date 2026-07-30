<?php

declare(strict_types=1);

namespace RowBuddy\Ratings\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;

/**
 * Raised when the submitting party matches neither the `Transfer`'s
 * `buyerId` nor its `sellerId` (ADR-024 §1) — only the transfer's own two
 * participants may rate each other; no third party may submit a rating
 * against it.
 */
final class RatingSubmissionNotAuthorized extends DomainException
{
    public static function forTransferId(string $transferId): self
    {
        return new self("Only the buyer or seller on transfer [{$transferId}] may submit a rating against it.");
    }
}
