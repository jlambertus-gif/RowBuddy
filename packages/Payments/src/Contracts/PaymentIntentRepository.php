<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Contracts;

use RowBuddy\Payments\PaymentIntent;

/**
 * Domain-facing persistence port. Deliberately expresses no ORM/storage
 * concept. Unlike Phase 4, `PaymentIntent` is no longer immutable-after-
 * creation — `save()` handles both the initial insert (authorize()/
 * declineAuthorization()) and every later transition (ADR-019 §1), the
 * same mutable-aggregate shape `AuctionRepository` uses, not
 * `BidRepository`'s append-only one.
 */
interface PaymentIntentRepository
{
    public function save(PaymentIntent $paymentIntent): void;

    public function findById(string $id): ?PaymentIntent;

    /**
     * The PaymentIntent already decided for this auction, if one exists —
     * the natural lookup given PaymentIntent is keyed by auctionId and
     * winningBidId (ADR-014), not by any reference to Auction's own
     * status.
     */
    public function findByAuctionId(string $auctionId): ?PaymentIntent;

    /**
     * Locks the payment intent row for the duration of the caller's
     * transaction (`SELECT ... FOR UPDATE`) — the serialization anchor
     * capture/cancellation rely on (ADR-019 §6). Callers must already be
     * inside a transaction; this method does not open one itself.
     */
    public function findByIdForUpdate(string $id): ?PaymentIntent;
}
