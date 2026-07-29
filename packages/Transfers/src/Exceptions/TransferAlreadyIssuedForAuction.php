<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;

/**
 * Enforces "one transfer per auction, no re-transfer" (ADR-017 §6) as a
 * defense-in-depth backstop — the primary guard is an application
 * service checking `TransferRepository::findByAuctionId()` before ever
 * issuing a new transfer, the same idempotency shape
 * `AuctionWinAuthorizationService` uses for `PaymentIntent`. This
 * exception only fires if that check is ever raced, translated from the
 * `auction_id` unique-constraint violation the same way
 * `PresenceSessionAlreadyConsumed` and `SellerPayoutAccountAlreadyLinked`
 * translate their own uniqueness constraints.
 */
final class TransferAlreadyIssuedForAuction extends DomainException
{
    public static function forAuctionId(string $auctionId): self
    {
        return new self("Auction [{$auctionId}] already has a transfer issued.");
    }
}
