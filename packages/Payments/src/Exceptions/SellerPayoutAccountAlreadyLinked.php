<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;

/**
 * A seller may have at most one linked Stripe Connect Express account —
 * enforced by a unique constraint on seller_id, translated here the same
 * way Auctions translates its own uniqueness constraint into
 * PresenceSessionAlreadyConsumed.
 */
final class SellerPayoutAccountAlreadyLinked extends DomainException
{
    public static function forSellerId(string $sellerId): self
    {
        return new self("Seller [{$sellerId}] already has a linked payout account.");
    }
}
