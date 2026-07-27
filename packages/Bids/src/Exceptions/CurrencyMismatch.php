<?php

declare(strict_types=1);

namespace RowBuddy\Bids\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;

final class CurrencyMismatch extends DomainException
{
    public static function forAuction(string $auctionId): self
    {
        return new self("Bid currency does not match the auction's currency for auction [{$auctionId}].");
    }
}
