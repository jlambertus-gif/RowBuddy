<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Contracts;

use RowBuddy\Payments\PaymentIntent;

/**
 * Domain-facing persistence port. Deliberately expresses no ORM/storage
 * concept, and deliberately exposes no update path — in Phase 4, a
 * PaymentIntent is fully decided (Authorized or Failed, per ADR-015)
 * at the moment it is created and never mutated afterward, the same
 * append-only shape Bids' own bid-repository contract uses for the same
 * reason (a Bid is immutable once recorded). This aggregate has no
 * dependency, direct or otherwise, on the Bids package — the comparison
 * above is stylistic, not a code reference.
 */
interface PaymentIntentRepository
{
    public function record(PaymentIntent $paymentIntent): void;

    public function findById(string $id): ?PaymentIntent;

    /**
     * The PaymentIntent already decided for this auction, if one exists —
     * the natural lookup given PaymentIntent is keyed by auctionId and
     * winningBidId (ADR-014), not by any reference to Auction's own
     * status.
     */
    public function findByAuctionId(string $auctionId): ?PaymentIntent;
}
