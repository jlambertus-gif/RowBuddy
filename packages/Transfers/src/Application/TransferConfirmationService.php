<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\Application;

use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Exceptions\NotFoundException;
use RowBuddy\SharedKernel\ValueObjects\GeoPoint;
use RowBuddy\Transfers\Contracts\DomainEventPublisher;
use RowBuddy\Transfers\Contracts\PaymentCaptureGateway;
use RowBuddy\Transfers\Contracts\TransactionManager;
use RowBuddy\Transfers\Contracts\TransferGeofenceLookup;
use RowBuddy\Transfers\Contracts\TransferRepository;
use RowBuddy\Transfers\Exceptions\ConfirmationOutsideGeofence;
use RowBuddy\Transfers\Exceptions\InvalidQrToken;
use RowBuddy\Transfers\Transfer;
use RowBuddy\Transfers\ValueObjects\TransferStatus;

/**
 * Orchestrates seller/buyer handoff confirmation (ADR-017 §4, ADR-020
 * §2). Confirmation requires both parties independently — only the
 * second confirmation to land transitions the `Transfer` to `Confirmed`
 * and triggers the real capture (ADR-019 §6).
 *
 * The geofence cross-check (ADR-020 §2) and, for the seller, the QR
 * token match (ADR-017 §5) both gate whether the aggregate's own
 * `confirmBySeller()`/`confirmByBuyer()` is ever called — a rejected
 * attempt never reaches it, mirroring `BidService`'s rejection shape.
 *
 * The `Transfer`-row lock and mutation commit as their own, independent
 * transaction *before* the capture trigger is ever attempted — a
 * legitimate, already-confirmed handoff must never be rolled back merely
 * because the *later*, separate capture step fails or errors (mirroring
 * ADR-012 §1a's "expected rejection vs. unexpected failure" principle,
 * applied here to keep two genuinely separate business facts from being
 * coupled into one all-or-nothing transaction).
 */
final class TransferConfirmationService
{
    public function __construct(
        private readonly TransferRepository $transfers,
        private readonly TransferGeofenceLookup $geofenceLookup,
        private readonly PaymentCaptureGateway $captureGateway,
        private readonly TransactionManager $transactions,
        private readonly DomainEventPublisher $events,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * @throws NotFoundException
     * @throws ConfirmationOutsideGeofence
     * @throws InvalidQrToken
     */
    public function confirmBySeller(string $transferId, string $qrToken, GeoPoint $geo): void
    {
        $transfer = $this->confirmWithinTransaction($transferId, $geo, function (Transfer $transfer) use ($qrToken, $geo): void {
            $this->assertQrTokenMatches($transfer, $qrToken);
            $transfer->confirmBySeller($geo, $this->clock);
        });

        $this->triggerCaptureIfConfirmed($transfer);
    }

    /**
     * @throws NotFoundException
     * @throws ConfirmationOutsideGeofence
     */
    public function confirmByBuyer(string $transferId, GeoPoint $geo): void
    {
        $transfer = $this->confirmWithinTransaction($transferId, $geo, function (Transfer $transfer) use ($geo): void {
            $transfer->confirmByBuyer($geo, $this->clock);
        });

        $this->triggerCaptureIfConfirmed($transfer);
    }

    /**
     * @throws NotFoundException
     * @throws ConfirmationOutsideGeofence
     */
    private function confirmWithinTransaction(string $transferId, GeoPoint $geo, callable $mutate): Transfer
    {
        [$transfer, $events] = $this->transactions->run(function () use ($transferId, $geo, $mutate) {
            $transfer = $this->transfers->findByIdForUpdate($transferId);

            if ($transfer === null) {
                throw new NotFoundException("Transfer [{$transferId}] not found.");
            }

            $geofence = $this->geofenceLookup->geofenceForAuction($transfer->auctionId);

            if (! $geofence->contains($geo)) {
                throw ConfirmationOutsideGeofence::forTransfer($transferId);
            }

            $mutate($transfer);
            $this->transfers->save($transfer);

            return [$transfer, $transfer->releaseEvents()];
        });

        foreach ($events as $event) {
            $this->events->publish($event);
        }

        return $transfer;
    }

    private function triggerCaptureIfConfirmed(Transfer $transfer): void
    {
        if ($transfer->status() === TransferStatus::Confirmed) {
            $this->captureGateway->capture($transfer->auctionId);
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
