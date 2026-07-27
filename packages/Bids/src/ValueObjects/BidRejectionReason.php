<?php

declare(strict_types=1);

namespace RowBuddy\Bids\ValueObjects;

/**
 * The set of expected, anticipated reasons a bid may be rejected
 * (ADR-012 §1a) — distinct from an unexpected infrastructure failure,
 * which is never represented here and instead propagates as an uncaught
 * exception.
 */
enum BidRejectionReason
{
    case AuctionNotFound;
    case AuctionNotOpenForBidding;
    case CurrencyMismatch;
    case BidTooLow;
    case SellerCannotBidOnOwnAuction;
}
