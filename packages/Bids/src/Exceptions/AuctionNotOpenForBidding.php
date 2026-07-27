<?php

declare(strict_types=1);

namespace RowBuddy\Bids\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;

final class AuctionNotOpenForBidding extends DomainException
{
    public static function forAuction(string $auctionId): self
    {
        return new self("Auction [{$auctionId}] is not open for bidding.");
    }
}
