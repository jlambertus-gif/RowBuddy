<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;

/**
 * Raised when a suspended account attempts to submit a new queue for
 * approval (ADR-026 §4) — deliberately separate from
 * {@see QueueSubmissionBlocked}, which represents a category/
 * jurisdiction legal gate, not an account-standing one.
 */
final class SubmitterAccountSuspended extends DomainException
{
    public static function forSubmitter(string $submittedByUserId): self
    {
        return new self("Submitter [{$submittedByUserId}]'s account is suspended and may not submit new queues.");
    }
}
