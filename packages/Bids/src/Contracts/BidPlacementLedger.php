<?php

declare(strict_types=1);

namespace RowBuddy\Bids\Contracts;

use RowBuddy\Bids\Exceptions\BidIdempotencyKeyReused;
use RowBuddy\Bids\Exceptions\BidPlacementInProgress;
use RowBuddy\Bids\ValueObjects\BidClaimResult;
use RowBuddy\Bids\ValueObjects\BidRejectionReason;

/**
 * Domain-facing persistence port for HTTP-retry idempotency (Phase 9,
 * ADR-027 Architecture Refinements §2) — deliberately separate from
 * {@see BidRepository}: a bid itself is a business record, while a claim
 * here only remembers "has this exact request already been attempted,"
 * the same separation Payments draws between its own idempotency ledger
 * (`ProcessedWebhookEvent`/`WebhookEventRepository`) and its payment
 * records — packages/Bids has no dependency on packages/Payments; the
 * comparison is stylistic, not a code reference.
 */
interface BidPlacementLedger
{
    /**
     * Atomically claims (bidderId, idempotencyKey) for this request. A
     * fresh claim means the caller must now place the bid and report the
     * outcome back via recordAccepted()/recordRejected(). A resolved claim
     * means an earlier identical request already ran to completion — the
     * caller must replay that outcome, never place a second bid.
     *
     * @throws BidIdempotencyKeyReused if the same key was already claimed
     *                                 for a different auction or amount
     * @throws BidPlacementInProgress if the same key is claimed but the
     *                                original request has not resolved yet
     */
    public function claim(string $bidderId, string $idempotencyKey, string $auctionId, string $requestFingerprint): BidClaimResult;

    public function recordAccepted(string $bidderId, string $idempotencyKey, string $bidId): void;

    public function recordRejected(string $bidderId, string $idempotencyKey, BidRejectionReason $reason): void;

    /**
     * Releases a claim that could not be resolved due to an unexpected
     * failure (not a known bid rejection) — safe because BidService's own
     * transaction guarantees nothing was actually persisted, so a genuine
     * retry may claim the key again.
     */
    public function release(string $bidderId, string $idempotencyKey): void;
}
