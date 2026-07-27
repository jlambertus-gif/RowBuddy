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

    public static function forAuctionAlreadyAtRisk(string $auctionId): self
    {
        return new self("Auction [{$auctionId}] is already flagged as proximity-at-risk.");
    }

    public static function forAuctionNotAtRisk(string $auctionId): self
    {
        return new self("Auction [{$auctionId}] is not currently flagged as proximity-at-risk.");
    }
}
