<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;
use RowBuddy\Transfers\ValueObjects\TransferStatus;

final class IllegalStateTransition extends DomainException
{
    public static function forTransfer(
        string $transferId,
        string $attemptedTransition,
        TransferStatus $currentStatus,
    ): self {
        return new self(
            "Transfer [{$transferId}] cannot {$attemptedTransition} while in status [{$currentStatus->value}]."
        );
    }

    public static function forTransferAlreadyConfirmedBySeller(string $transferId): self
    {
        return new self("Transfer [{$transferId}] has already been confirmed by the seller.");
    }

    public static function forTransferAlreadyConfirmedByBuyer(string $transferId): self
    {
        return new self("Transfer [{$transferId}] has already been confirmed by the buyer.");
    }
}
