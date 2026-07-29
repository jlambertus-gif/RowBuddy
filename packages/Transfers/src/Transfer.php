<?php

declare(strict_types=1);

namespace RowBuddy\Transfers;

use DateTimeImmutable;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Contracts\DomainEvent;
use RowBuddy\SharedKernel\ValueObjects\GeoPoint;
use RowBuddy\Transfers\Events\TransferBuyerConfirmed;
use RowBuddy\Transfers\Events\TransferCancelled;
use RowBuddy\Transfers\Events\TransferConfirmed;
use RowBuddy\Transfers\Events\TransferEvidenceAttached;
use RowBuddy\Transfers\Events\TransferExpired;
use RowBuddy\Transfers\Events\TransferIssued;
use RowBuddy\Transfers\Events\TransferSellerConfirmed;
use RowBuddy\Transfers\Exceptions\IllegalStateTransition;
use RowBuddy\Transfers\ValueObjects\TransferEvidenceRecord;
use RowBuddy\Transfers\ValueObjects\TransferEvidenceType;
use RowBuddy\Transfers\ValueObjects\TransferStatus;

/**
 * Aggregate root for the handoff of a won auction's position from seller
 * to buyer (Phase 5, ADR-017/018/019/020). Tracks its own lifecycle keyed
 * by auctionId/winningBidId, never by reading Auction's own status —
 * auctionId, winningBidId, sellerId, and buyerId arrive here as
 * already-decided facts sourced from Payments' PaymentAuthorized event,
 * the same way Payments itself never derives facts it receives from
 * AuctionWon (ADR-014, extended one hop further by ADR-017 §2).
 *
 * Confirmation requires BOTH parties independently (ADR-017 §4) — only
 * the second confirmation to land transitions the aggregate to Confirmed
 * and fires the real capture trigger (ADR-019). Geofence and QR
 * validation are not this aggregate's concern: a rejected confirmation
 * attempt (wrong QR, outside the geofence) never reaches
 * confirmBySeller()/confirmByBuyer() at all (ADR-017 §4, ADR-020 §2) —
 * the same "rejection leaves no trace" shape Bids uses for an invalid
 * bid attempt.
 *
 * Evidence (ADR-020 §4) is a first-class, repeatable concept independent
 * of the confirmation state machine — attachEvidence() is callable
 * regardless of status, since a future dispute may need to attach
 * evidence long after this transfer reached a terminal state.
 */
final class Transfer
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    /** @var list<TransferEvidenceRecord> */
    private array $evidenceRecords = [];

    private function __construct(
        public readonly string $id,
        public readonly string $auctionId,
        public readonly string $winningBidId,
        public readonly string $sellerId,
        public readonly string $buyerId,
        public readonly string $qrTokenHash,
        public readonly DateTimeImmutable $issuedAt,
        public readonly DateTimeImmutable $expiresAt,
        private TransferStatus $status,
        private ?DateTimeImmutable $sellerConfirmedAt = null,
        private ?GeoPoint $sellerConfirmedGeo = null,
        private ?DateTimeImmutable $buyerConfirmedAt = null,
        private ?GeoPoint $buyerConfirmedGeo = null,
        private ?DateTimeImmutable $confirmedAt = null,
    ) {}

    /**
     * `expiresAt` arrives already computed (ADR-018 §1) — this aggregate
     * never knows or derives the transfer-window duration, the same
     * discipline `Auction::open()` uses for `closesAt`. `qrTokenHash`
     * arrives already hashed — the aggregate never sees or stores the
     * plaintext QR value (ADR-017 §2/§5).
     */
    public static function issue(
        string $id,
        string $auctionId,
        string $winningBidId,
        string $sellerId,
        string $buyerId,
        string $qrTokenHash,
        DateTimeImmutable $expiresAt,
        ClockInterface $clock,
    ): self {
        $transfer = new self(
            id: $id,
            auctionId: $auctionId,
            winningBidId: $winningBidId,
            sellerId: $sellerId,
            buyerId: $buyerId,
            qrTokenHash: $qrTokenHash,
            issuedAt: $clock->now(),
            expiresAt: $expiresAt,
            status: TransferStatus::Issued,
        );

        $transfer->recordedEvents[] = new TransferIssued(
            $clock,
            $id,
            $auctionId,
            $winningBidId,
            $sellerId,
            $buyerId,
        );

        return $transfer;
    }

    /**
     * Reconstitutes a Transfer from previously persisted state. Unlike
     * issue() above, this never raises domain events — loading a
     * Transfer back out of storage is not a business event in itself.
     *
     * @param  list<TransferEvidenceRecord>  $evidenceRecords
     */
    public static function fromPersistence(
        string $id,
        string $auctionId,
        string $winningBidId,
        string $sellerId,
        string $buyerId,
        string $qrTokenHash,
        DateTimeImmutable $issuedAt,
        DateTimeImmutable $expiresAt,
        TransferStatus $status,
        ?DateTimeImmutable $sellerConfirmedAt,
        ?GeoPoint $sellerConfirmedGeo,
        ?DateTimeImmutable $buyerConfirmedAt,
        ?GeoPoint $buyerConfirmedGeo,
        ?DateTimeImmutable $confirmedAt,
        array $evidenceRecords = [],
    ): self {
        $transfer = new self(
            id: $id,
            auctionId: $auctionId,
            winningBidId: $winningBidId,
            sellerId: $sellerId,
            buyerId: $buyerId,
            qrTokenHash: $qrTokenHash,
            issuedAt: $issuedAt,
            expiresAt: $expiresAt,
            status: $status,
            sellerConfirmedAt: $sellerConfirmedAt,
            sellerConfirmedGeo: $sellerConfirmedGeo,
            buyerConfirmedAt: $buyerConfirmedAt,
            buyerConfirmedGeo: $buyerConfirmedGeo,
            confirmedAt: $confirmedAt,
        );

        $transfer->evidenceRecords = $evidenceRecords;

        return $transfer;
    }

    public function status(): TransferStatus
    {
        return $this->status;
    }

    public function sellerConfirmedAt(): ?DateTimeImmutable
    {
        return $this->sellerConfirmedAt;
    }

    public function sellerConfirmedGeo(): ?GeoPoint
    {
        return $this->sellerConfirmedGeo;
    }

    public function buyerConfirmedAt(): ?DateTimeImmutable
    {
        return $this->buyerConfirmedAt;
    }

    public function buyerConfirmedGeo(): ?GeoPoint
    {
        return $this->buyerConfirmedGeo;
    }

    public function confirmedAt(): ?DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    /**
     * @throws IllegalStateTransition
     */
    public function confirmBySeller(GeoPoint $geo, ClockInterface $clock): void
    {
        $this->guardStatus(TransferStatus::Issued, 'confirm as seller');

        if ($this->sellerConfirmedAt !== null) {
            throw IllegalStateTransition::forTransferAlreadyConfirmedBySeller($this->id);
        }

        $this->sellerConfirmedAt = $clock->now();
        $this->sellerConfirmedGeo = $geo;
        $this->recordedEvents[] = new TransferSellerConfirmed($clock, $this->id, $geo);

        $this->completeIfBothConfirmed($clock);
    }

    /**
     * @throws IllegalStateTransition
     */
    public function confirmByBuyer(GeoPoint $geo, ClockInterface $clock): void
    {
        $this->guardStatus(TransferStatus::Issued, 'confirm as buyer');

        if ($this->buyerConfirmedAt !== null) {
            throw IllegalStateTransition::forTransferAlreadyConfirmedByBuyer($this->id);
        }

        $this->buyerConfirmedAt = $clock->now();
        $this->buyerConfirmedGeo = $geo;
        $this->recordedEvents[] = new TransferBuyerConfirmed($clock, $this->id, $geo);

        $this->completeIfBothConfirmed($clock);
    }

    /**
     * The transfer window closed with no confirmation from both parties
     * (ADR-018 §3) — distinct from cancel(), which represents an
     * explicit default/failure rather than simply running out the clock.
     *
     * @throws IllegalStateTransition
     */
    public function expire(ClockInterface $clock): void
    {
        $this->guardStatus(TransferStatus::Issued, 'expire');

        $this->status = TransferStatus::Expired;
        $this->recordedEvents[] = new TransferExpired($clock, $this->id, $this->auctionId);
    }

    /**
     * An explicit default or failure short-circuits the transfer window
     * (ADR-018 §2/§4) — e.g. re-authorization failed, or a party is known
     * to have abandoned the handoff before the window itself elapsed.
     *
     * @throws IllegalStateTransition
     */
    public function cancel(string $reason, ClockInterface $clock): void
    {
        $this->guardStatus(TransferStatus::Issued, 'cancel');

        $this->status = TransferStatus::Cancelled;
        $this->recordedEvents[] = new TransferCancelled($clock, $this->id, $this->auctionId, $reason);
    }

    /**
     * First-class, repeatable evidence (ADR-020 §4) — deliberately not
     * guarded by status; a future dispute may need to attach evidence
     * long after this transfer reached a terminal state.
     */
    public function attachEvidence(
        TransferEvidenceType $type,
        string $storageReference,
        string $submittedBy,
        ClockInterface $clock,
    ): void {
        $record = new TransferEvidenceRecord($type, $storageReference, $submittedBy, $clock->now());
        $this->evidenceRecords[] = $record;
        $this->recordedEvents[] = new TransferEvidenceAttached(
            $clock,
            $this->id,
            $type,
            $storageReference,
            $submittedBy,
        );
    }

    /**
     * @return list<TransferEvidenceRecord>
     */
    public function evidenceRecords(): array
    {
        return $this->evidenceRecords;
    }

    /**
     * @return list<DomainEvent>
     */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    private function completeIfBothConfirmed(ClockInterface $clock): void
    {
        if ($this->sellerConfirmedAt !== null && $this->buyerConfirmedAt !== null) {
            $this->status = TransferStatus::Confirmed;
            $this->confirmedAt = $clock->now();
            $this->recordedEvents[] = new TransferConfirmed($clock, $this->id, $this->auctionId, $this->winningBidId);
        }
    }

    /**
     * @throws IllegalStateTransition
     */
    private function guardStatus(TransferStatus $expected, string $attemptedTransition): void
    {
        if ($this->status !== $expected) {
            throw IllegalStateTransition::forTransfer($this->id, $attemptedTransition, $this->status);
        }
    }
}
