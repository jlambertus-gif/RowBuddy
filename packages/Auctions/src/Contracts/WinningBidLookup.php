<?php

declare(strict_types=1);

namespace RowBuddy\Auctions\Contracts;

use RowBuddy\Auctions\ValueObjects\WinningBidCandidate;

/**
 * Domain-facing read port (ADR-013 §5): lets Auctions ask "what is the
 * current highest bid for this auction?" without depending on Bids'
 * Eloquent models, repositories, or concrete services. Owned by Auctions,
 * in Auctions' own vocabulary — implemented by an apps/web
 * composition-root adapter bridging to Bids' own BidRepository. Mirrors
 * ADR-009's SellerPresenceVerification shape exactly, in the opposite
 * direction: packages/Auctions gains no dependency on packages/Bids.
 */
interface WinningBidLookup
{
    /**
     * Must reflect the same deterministic ordering as Bids' own
     * highest-bid query (amount DESC, placedAt ASC, id ASC) — a tie is
     * structurally impossible today (Bids requires a strictly-greater
     * raise), but the contract does not rely on that holding forever.
     */
    public function highestBidFor(string $auctionId): ?WinningBidCandidate;
}
