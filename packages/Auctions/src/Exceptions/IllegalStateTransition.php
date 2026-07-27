<?php

declare(strict_types=1);

namespace RowBuddy\Auctions\Exceptions;

use RowBuddy\Auctions\ValueObjects\AuctionStatus;
use RowBuddy\SharedKernel\Exceptions\DomainException;

final class IllegalStateTransition extends DomainException
{
    public static function forAuction(
        string $auctionId,
        string $attemptedTransition,
        AuctionStatus $currentStatus,
    ): self {
        return new self(
            "Auction [{$auctionId}] cannot {$attemptedTransition} while in status [{$currentStatus->value}]."
        );
    }
}
