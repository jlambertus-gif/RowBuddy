<?php

declare(strict_types=1);

namespace RowBuddy\Disputes;

use DateTimeImmutable;
use RowBuddy\Disputes\Events\DisputeEvidenceAttached;
use RowBuddy\Disputes\Events\DisputeOpened;
use RowBuddy\Disputes\Events\DisputeResolved;
use RowBuddy\Disputes\Exceptions\IllegalStateTransition;
use RowBuddy\Disputes\Exceptions\InvalidDisputeResolution;
use RowBuddy\Disputes\ValueObjects\DisputeEvidenceRecord;
use RowBuddy\Disputes\ValueObjects\DisputeEvidenceType;
use RowBuddy\Disputes\ValueObjects\DisputeResolutionOutcome;
use RowBuddy\Disputes\ValueObjects\DisputeStatus;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Contracts\DomainEvent;
use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * Aggregate root for a buyer-filed dispute over a confirmed handoff
 * (Phase 6, ADR-021/022/023). Tracks its own lifecycle keyed by
 * `transferId`/`auctionId`, never by reading `Transfer`'s own status —
 * `transferId`, `auctionId`, `buyerId`, and `sellerId` arrive here as
 * already-decided facts the caller resolved (including the
 * `Confirmed`-only eligibility gate, ADR-021 §2), the same discipline
 * `Transfer` itself uses for facts sourced from `PaymentAuthorized`.
 *
 * Only two statuses exist (ADR-021 §1's own docblock, `DisputeStatus`):
 * `Opened` (filed, receiving the seller's response and any evidence) and
 * `Resolved` (terminal — no reopening, no appeal, no second review
 * state, ADR-021 §5). The response deadline (ADR-021 §4) is never a
 * stored transition; it is a lazily-checked fact an application service
 * computes from `openedAt`, not a status this aggregate tracks.
 *
 * `resolve()` is the only way to leave `Opened`, and it is always an
 * explicit administrator action (ADR-021 §6) — this aggregate never
 * computes, scores, or infers an outcome itself.
 */
final class Dispute
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    /** @var list<DisputeEvidenceRecord> */
    private array $evidenceRecords = [];

    private function __construct(
        public readonly string $id,
        public readonly string $transferId,
        public readonly string $auctionId,
        public readonly string $buyerId,
        public readonly string $sellerId,
        public readonly string $reason,
        public readonly DateTimeImmutable $openedAt,
        private DisputeStatus $status,
        private ?DisputeResolutionOutcome $resolutionOutcome = null,
        private ?Money $refundAmount = null,
        private ?string $resolvedBy = null,
        private ?string $resolutionNotes = null,
        private bool $evidenceFoundFraudulent = false,
        private ?DateTimeImmutable $resolvedAt = null,
    ) {}

    public static function open(
        string $id,
        string $transferId,
        string $auctionId,
        string $buyerId,
        string $sellerId,
        string $reason,
        ClockInterface $clock,
    ): self {
        $dispute = new self(
            id: $id,
            transferId: $transferId,
            auctionId: $auctionId,
            buyerId: $buyerId,
            sellerId: $sellerId,
            reason: $reason,
            openedAt: $clock->now(),
            status: DisputeStatus::Opened,
        );

        $dispute->recordedEvents[] = new DisputeOpened(
            $clock,
            $id,
            $transferId,
            $auctionId,
            $buyerId,
            $sellerId,
            $reason,
        );

        return $dispute;
    }

    /**
     * Reconstitutes a Dispute from previously persisted state. Unlike
     * open() above, this never raises domain events — loading a Dispute
     * back out of storage is not a business event in itself.
     *
     * @param  list<DisputeEvidenceRecord>  $evidenceRecords
     */
    public static function fromPersistence(
        string $id,
        string $transferId,
        string $auctionId,
        string $buyerId,
        string $sellerId,
        string $reason,
        DateTimeImmutable $openedAt,
        DisputeStatus $status,
        ?DisputeResolutionOutcome $resolutionOutcome,
        ?Money $refundAmount,
        ?string $resolvedBy,
        ?string $resolutionNotes,
        bool $evidenceFoundFraudulent,
        ?DateTimeImmutable $resolvedAt,
        array $evidenceRecords = [],
    ): self {
        $dispute = new self(
            id: $id,
            transferId: $transferId,
            auctionId: $auctionId,
            buyerId: $buyerId,
            sellerId: $sellerId,
            reason: $reason,
            openedAt: $openedAt,
            status: $status,
            resolutionOutcome: $resolutionOutcome,
            refundAmount: $refundAmount,
            resolvedBy: $resolvedBy,
            resolutionNotes: $resolutionNotes,
            evidenceFoundFraudulent: $evidenceFoundFraudulent,
            resolvedAt: $resolvedAt,
        );

        $dispute->evidenceRecords = $evidenceRecords;

        return $dispute;
    }

    public function status(): DisputeStatus
    {
        return $this->status;
    }

    public function resolutionOutcome(): ?DisputeResolutionOutcome
    {
        return $this->resolutionOutcome;
    }

    public function refundAmount(): ?Money
    {
        return $this->refundAmount;
    }

    public function resolvedBy(): ?string
    {
        return $this->resolvedBy;
    }

    public function resolutionNotes(): ?string
    {
        return $this->resolutionNotes;
    }

    public function evidenceFoundFraudulent(): bool
    {
        return $this->evidenceFoundFraudulent;
    }

    public function resolvedAt(): ?DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    /**
     * Either party (the filing buyer or the respondent seller) may
     * attach evidence while the case is open (ADR-021 §1) — this
     * aggregate does not itself distinguish who submitted it beyond the
     * `submittedBy` id on the record, mirroring `Transfer`'s own
     * evidence model (ADR-020 §4). Deliberately guarded to `Opened`,
     * unlike `Transfer::attachEvidence()`: a `Dispute`'s own evidence
     * must close off once the case itself is resolved, or "no
     * reopening" (ADR-021 §5) would be meaningless.
     *
     * @throws IllegalStateTransition
     */
    public function attachEvidence(
        DisputeEvidenceType $type,
        string $storageReference,
        string $submittedBy,
        ClockInterface $clock,
    ): void {
        $this->guardStatus(DisputeStatus::Opened, 'attach evidence');

        $record = new DisputeEvidenceRecord($type, $storageReference, $submittedBy, $clock->now());
        $this->evidenceRecords[] = $record;
        $this->recordedEvents[] = new DisputeEvidenceAttached(
            $clock,
            $this->id,
            $type,
            $storageReference,
            $submittedBy,
        );
    }

    /**
     * @return list<DisputeEvidenceRecord>
     */
    public function evidenceRecords(): array
    {
        return $this->evidenceRecords;
    }

    /**
     * The only way to leave `Opened` (ADR-021 §5/§6) — always an
     * explicit administrator decision; this method never selects an
     * outcome itself. `refundAmount` must be present (and positive) for
     * `RefundToBuyer`/`Split`, and absent for `ReleaseToSeller`/
     * `Cancelled` — the only invariant this aggregate can enforce
     * without knowing the captured total (ADR-022 §2 owns the
     * amount-cannot-exceed-capture invariant, at `PaymentIntent::refund()`).
     *
     * `evidenceFoundFraudulent` is recorded as an inert observation
     * (ADR-021 §8) — it has no effect on the outcome, on any other
     * dispute, or on anything outside this aggregate.
     *
     * @throws IllegalStateTransition
     * @throws InvalidDisputeResolution
     */
    public function resolve(
        DisputeResolutionOutcome $outcome,
        ?Money $refundAmount,
        string $resolvedBy,
        string $resolutionNotes,
        bool $evidenceFoundFraudulent,
        ClockInterface $clock,
    ): void {
        $this->guardStatus(DisputeStatus::Opened, 'resolve');

        $requiresAmount = $outcome === DisputeResolutionOutcome::RefundToBuyer
            || $outcome === DisputeResolutionOutcome::Split;

        if ($requiresAmount && ($refundAmount === null || $refundAmount->isZero())) {
            throw InvalidDisputeResolution::amountRequiredForOutcome($this->id, $outcome->value);
        }

        if (! $requiresAmount && $refundAmount !== null) {
            throw InvalidDisputeResolution::amountNotAllowedForOutcome($this->id, $outcome->value);
        }

        $this->status = DisputeStatus::Resolved;
        $this->resolutionOutcome = $outcome;
        $this->refundAmount = $refundAmount;
        $this->resolvedBy = $resolvedBy;
        $this->resolutionNotes = $resolutionNotes;
        $this->evidenceFoundFraudulent = $evidenceFoundFraudulent;
        $this->resolvedAt = $clock->now();

        $this->recordedEvents[] = new DisputeResolved(
            $clock,
            $this->id,
            $outcome,
            $refundAmount,
            $resolvedBy,
            $resolutionNotes,
            $evidenceFoundFraudulent,
        );
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

    /**
     * @throws IllegalStateTransition
     */
    private function guardStatus(DisputeStatus $expected, string $attemptedTransition): void
    {
        if ($this->status !== $expected) {
            throw IllegalStateTransition::forDispute($this->id, $attemptedTransition, $this->status);
        }
    }
}
