<?php

declare(strict_types=1);

namespace RowBuddy\Bids\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;

final class BidTooLow extends DomainException
{
    public static function forAuction(string $auctionId): self
    {
        return new self("Bid amount is not high enough to win auction [{$auctionId}].");
    }
}
