<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\Application;

use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\Transfers\Contracts\DomainEventPublisher;
use RowBuddy\Transfers\Contracts\TransferRepository;
use RowBuddy\Transfers\Contracts\TransferWindowPolicy;
use RowBuddy\Transfers\Transfer;
use RowBuddy\Transfers\ValueObjects\TransferIssuance;

/**
 * The real `PaymentAuthorized` consumer — a `Transfer`/QR must never be
 * issuable for a bid that won but whose payment failed to authorize, so
 * this service reacts to Payments' `PaymentAuthorized` event, never
 * `AuctionWon` directly, mirroring the shape
 * `AuctionWinAuthorizationService` (Payments, Phase 4) established for
 * reacting to another module's domain event directly rather than via a
 * synchronous gateway.
 *
 * Idempotent via `findByAuctionId()` — the same shape
 * `AuctionWinAuthorizationService` uses, needing no lock: a duplicate
 * `auction_id` insert is additionally guarded by the database's own
 * unique constraint (`TransferAlreadyIssuedForAuction`, ADR-017 §6).
 *
 * No caller wires this to a real Laravel event listener yet — mirroring
 * `AuctionWinAuthorizationService`'s own shape in Phase 4 Sprint 4, which
 * likewise has no real `AuctionWon` listener registered in apps/web to
 * this day. That wiring is delivery-layer work for a later sprint.
 */
final class TransferInitiationService
{
    public function __construct(
        private readonly TransferRepository $transfers,
        private readonly TransferWindowPolicy $windowPolicy,
        private readonly DomainEventPublisher $events,
        private readonly ClockInterface $clock,
    ) {}

    public function handle(
        string $transferId,
        string $auctionId,
        string $winningBidId,
        string $sellerId,
        string $buyerId,
    ): TransferIssuance {
        $existing = $this->transfers->findByAuctionId($auctionId);

        if ($existing !== null) {
            return new TransferIssuance($existing, null);
        }

        $plaintextQrToken = bin2hex(random_bytes(32));
        $qrTokenHash = hash('sha256', $plaintextQrToken);
        $durationInSeconds = $this->windowPolicy->durationInSecondsFor($auctionId);
        $expiresAt = $this->clock->now()->modify("+{$durationInSeconds} seconds");

        $transfer = Transfer::issue(
            $transferId,
            $auctionId,
            $winningBidId,
            $sellerId,
            $buyerId,
            $qrTokenHash,
            $expiresAt,
            $this->clock,
        );

        $this->transfers->save($transfer);

        foreach ($transfer->releaseEvents() as $event) {
            $this->events->publish($event);
        }

        return new TransferIssuance($transfer, $plaintextQrToken);
    }
}
