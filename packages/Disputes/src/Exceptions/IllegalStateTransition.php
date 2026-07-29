<?php

declare(strict_types=1);

namespace RowBuddy\Disputes\Exceptions;

use RowBuddy\Disputes\ValueObjects\DisputeStatus;
use RowBuddy\SharedKernel\Exceptions\DomainException;

final class IllegalStateTransition extends DomainException
{
    public static function forDispute(
        string $disputeId,
        string $attemptedTransition,
        DisputeStatus $currentStatus,
    ): self {
        return new self(
            "Dispute [{$disputeId}] cannot {$attemptedTransition} while in status [{$currentStatus->value}]."
        );
    }
}
