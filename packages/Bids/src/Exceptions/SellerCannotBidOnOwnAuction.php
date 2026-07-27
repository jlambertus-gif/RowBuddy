<?php

declare(strict_types=1);

namespace RowBuddy\Bids\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;

final class SellerCannotBidOnOwnAuction extends DomainException
{
    public static function forAuction(string $auctionId): self
    {
        return new self("The seller of auction [{$auctionId}] cannot bid on their own auction.");
    }
}
