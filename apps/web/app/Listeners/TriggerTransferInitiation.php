<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Support\AuctionParticipantResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RowBuddy\Payments\Events\PaymentAuthorized;
use RowBuddy\Transfers\Application\TransferInitiationService;

/**
 * The first real `PaymentAuthorized` -> `TransferInitiationService` wiring
 * (Phase 9, ADR-027 Sprint 3) — this capability has existed since Phase 5
 * with no caller wiring it to a real event listener at all until now,
 * mirroring `TriggerAuctionWinAuthorization`'s own shape one hop further
 * down the chain. Additive composition-root wiring, not a replacement of
 * any existing direct-invocation orchestration.
 *
 * Delivers the one-time plaintext QR token `TransferInitiationService`
 * returns (never persisted by the Transfers package itself, ADR-017 §2)
 * to the buyer via a short-lived cache entry, keyed by transfer id, TTL
 * bounded by the transfer's own expiry — the delivery-layer mechanism
 * `TransferIssuance`'s own docblock explicitly flagged as not existing
 * yet. Nothing is cached on an idempotent replay (`plaintextQrToken` is
 * null): the plaintext is unrecoverable by design, and a duplicate
 * `PaymentAuthorized` delivery never re-issues a second Transfer anyway
 * (`TransferInitiationService::handle()`'s own `findByAuctionId()`
 * short-circuit).
 */
final class TriggerTransferInitiation implements ShouldQueue
{
    public function __construct(
        private readonly AuctionParticipantResolver $participants,
        private readonly TransferInitiationService $transferInitiation,
    ) {}

    public function handle(PaymentAuthorized $event): void
    {
        $payload = $event->payload();
        $auctionId = (string) $payload['auction_id'];
        $winningBidId = (string) $payload['winning_bid_id'];

        $resolved = $this->participants->resolve($auctionId, $winningBidId);

        $issuance = $this->transferInitiation->handle(
            (string) Str::uuid(),
            $auctionId,
            $winningBidId,
            $resolved->sellerId,
            $resolved->buyerId,
        );

        if ($issuance->plaintextQrToken !== null) {
            Cache::put(
                "transfers.{$issuance->transfer->id}.qr_token",
                $issuance->plaintextQrToken,
                $issuance->transfer->expiresAt,
            );
        }
    }
}
