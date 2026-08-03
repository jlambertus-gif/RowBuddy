<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;

/**
 * Raised when a user attempts to confirm a transfer as a participant they
 * are not — enforced in the application layer (not left to the HTTP layer
 * or a database constraint), mirroring QueuePresence's own
 * PresenceSessionAccessDenied posture: ownership is a domain-level rule,
 * not an infrastructure concern. No dependency on that package's own
 * exception class — the Laravel architecture preset forbids one bounded
 * context depending on another's internals, so this is a prose analogy,
 * not an import.
 */
final class TransferAccessDenied extends DomainException
{
    public static function forTransferId(string $transferId): self
    {
        return new self("You do not have access to transfer [{$transferId}].");
    }
}
