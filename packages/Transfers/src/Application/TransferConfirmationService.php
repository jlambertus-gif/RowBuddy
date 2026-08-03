<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\Application;

use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Exceptions\NotFoundException;
use RowBuddy\SharedKernel\ValueObjects\GeoPoint;
use RowBuddy\Transfers\Contracts\DomainEventPublisher;
use RowBuddy\Transfers\Contracts\TransactionManager;
use RowBuddy\Transfers\Contracts\TransferGeofenceLookup;
use RowBuddy\Transfers\Contracts\TransferRepository;
use RowBuddy\Transfers\Exceptions\ConfirmationOutsideGeofence;
use RowBuddy\Transfers\Exceptions\IllegalStateTransition;
use RowBuddy\Transfers\Exceptions\InvalidQrToken;
use RowBuddy\Transfers\Exceptions\TransferAccessDenied;
use RowBuddy\Transfers\Transfer;
use RowBuddy\Transfers\ValueObjects\TransferStatus;

/**
 * Orchestrates seller/buyer handoff confirmation (ADR-017 §4, ADR-020
 * §2). Confirmation requires both parties independently — only the
 * second confirmation to land transitions the `Transfer` to `Confirmed`.
 *
 * Deliberately limited to validation, domain mutation, persistence, and
 * event publication — it has no knowledge of Payments or capture.
 * Reacting to the resulting `TransferConfirmed` event (and triggering the
 * real Stripe capture, ADR-019 §6) is `TransferCaptureTriggerService`'s
 * job, invoked only after this service's transaction has committed and
 * the event has actually been published — never as a status check
 * inline here, which would couple an outbound side effect to this
 * service's own aggregate mutation rather than to the committed event.
 *
 * Also the lazy call site for `TransferExpiryEvaluator` (ADR-018 §3) —
 * any confirmation attempt touching an already-locked `Transfer` first
 * gets the same evaluation the scheduled sweep performs, catching the
 * common case (someone actually shows up before their deadline, or just
 * after it) without waiting for a sweep tick. If evaluation expires the
 * transfer, that expiry (and its `TransferExpired` event) still commits
 * — the confirmation attempt itself is rejected only *after* the
 * transaction returns, so a stale attempt can never roll back a
 * legitimate, independently-true expiry.
 *
 * The geofence cross-check (ADR-020 §2) and, for the seller, the QR
 * token match (ADR-017 §5) both gate whether the aggregate's own
 * `confirmBySeller()`/`confirmByBuyer()` is ever called — a rejected
 * attempt never reaches it, mirroring `BidService`'s rejection shape.
 *
 * `requestingUserId` is verified against the transfer's own
 * `sellerId`/`buyerId` before anything else happens — including before
 * the expiry evaluator runs (Phase 9, ADR-027 Architecture Refinements
 * §5) — the same "ownership enforced in the application layer" posture
 * `PresenceSessionService` already established (`assertOwnedBy()`);
 * `Transfer` the aggregate itself has no concept of a requesting user, by
 * design. An unrelated authenticated user (or the wrong-role participant,
 * e.g. the buyer calling `confirmBySeller()`) is rejected identically to
 * a stranger with no relationship to this transfer at all.
 */
final class TransferConfirmationService
{
    public function __construct(
        private readonly TransferRepository $transfers,
        private readonly TransferGeofenceLookup $geofenceLookup,
        private readonly TransferExpiryEvaluator $expiryEvaluator,
        private readonly TransactionManager $transactions,
        private readonly DomainEventPublisher $events,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * @throws NotFoundException
     * @throws TransferAccessDenied
     * @throws IllegalStateTransition
     * @throws ConfirmationOutsideGeofence
     * @throws InvalidQrToken
     */
    public function confirmBySeller(string $transferId, string $requestingUserId, string $qrToken, GeoPoint $geo): void
    {
        $this->confirmWithinTransaction(
            $transferId,
            $requestingUserId,
            $geo,
            static fn (Transfer $transfer): string => $transfer->sellerId,
            function (Transfer $transfer) use ($qrToken, $geo): void {
                $this->assertQrTokenMatches($transfer, $qrToken);
                $transfer->confirmBySeller($geo, $this->clock);
            },
        );
    }

    /**
     * @throws NotFoundException
     * @throws TransferAccessDenied
     * @throws IllegalStateTransition
     * @throws ConfirmationOutsideGeofence
     */
    public function confirmByBuyer(string $transferId, string $requestingUserId, GeoPoint $geo): void
    {
        $this->confirmWithinTransaction(
            $transferId,
            $requestingUserId,
            $geo,
            static fn (Transfer $transfer): string => $transfer->buyerId,
            function (Transfer $transfer) use ($geo): void {
                $transfer->confirmByBuyer($geo, $this->clock);
            },
        );
    }

    /**
     * @throws NotFoundException
     * @throws TransferAccessDenied
     * @throws IllegalStateTransition
     * @throws ConfirmationOutsideGeofence
     */
    private function confirmWithinTransaction(
        string $transferId,
        string $requestingUserId,
        GeoPoint $geo,
        callable $expectedParticipantId,
        callable $mutate,
    ): void {
        [$events, $rejection] = $this->transactions->run(function () use ($transferId, $requestingUserId, $geo, $expectedParticipantId, $mutate) {
            $transfer = $this->transfers->findByIdForUpdate($transferId);

            if ($transfer === null) {
                throw new NotFoundException("Transfer [{$transferId}] not found.");
            }

            if ($expectedParticipantId($transfer) !== $requestingUserId) {
                throw TransferAccessDenied::forTransferId($transferId);
            }

            $this->expiryEvaluator->evaluate($transfer);

            $rejection = null;

            if ($transfer->status() !== TransferStatus::Issued) {
                $rejection = IllegalStateTransition::forTransfer($transferId, 'confirm', $transfer->status());
            } else {
                $geofence = $this->geofenceLookup->geofenceForAuction($transfer->auctionId);

                if (! $geofence->contains($geo)) {
                    throw ConfirmationOutsideGeofence::forTransfer($transferId);
                }

                $mutate($transfer);
            }

            $this->transfers->save($transfer);

            return [$transfer->releaseEvents(), $rejection];
        });

        foreach ($events as $event) {
            $this->events->publish($event);
        }

        if ($rejection !== null) {
            throw $rejection;
        }
    }

    /**
     * @throws InvalidQrToken
     */
    private function assertQrTokenMatches(Transfer $transfer, string $qrToken): void
    {
        if (! hash_equals($transfer->qrTokenHash, hash('sha256', $qrToken))) {
            throw InvalidQrToken::forTransfer($transfer->id);
        }
    }
}
