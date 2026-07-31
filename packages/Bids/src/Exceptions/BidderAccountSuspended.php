<?php

declare(strict_types=1);

namespace RowBuddy\Bids\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;

/**
 * Raised when a suspended account attempts to place a bid (ADR-026 §4)
 * — checked and rejected before the auction lock's own effects ever
 * translate into a recorded `Bid` or a `BidPlaced` event.
 */
final class BidderAccountSuspended extends DomainException
{
    public static function forBidder(string $bidderId): self
    {
        return new self("Bidder [{$bidderId}]'s account is suspended and may not place bids.");
    }
}
