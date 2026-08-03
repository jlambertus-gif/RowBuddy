<?php

declare(strict_types=1);

namespace App\Exceptions;

use RowBuddy\Payments\BuyerPaymentMethod;
use RuntimeException;

/**
 * Raised when `TriggerAuctionWinAuthorization` reacts to an `AuctionWon`
 * event for a buyer with no saved {@see BuyerPaymentMethod}
 * on record (Phase 9, ADR-027 Sprint 3) — an explicit, documented failure
 * path: this listener is queued, so an uncaught exception here lands in
 * Laravel's own `failed_jobs`, the same bounded-retry posture ADR-025 §11
 * already established, rather than a bespoke failure-tracking mechanism.
 */
final class MissingBuyerPaymentMethod extends RuntimeException
{
    public static function forBuyer(string $buyerId, string $auctionId): self
    {
        return new self("Buyer [{$buyerId}] has no saved payment method to authorize auction [{$auctionId}]'s winning bid.");
    }
}
