<?php

declare(strict_types=1);

namespace RowBuddy\Disputes\Events;

use RowBuddy\Disputes\ValueObjects\DisputeResolutionOutcome;
use RowBuddy\SharedKernel\Contracts\AuditableAction;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Events\AbstractDomainEvent;
use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * The one and only resolution event (ADR-021 §5/§6) — `Dispute` has no
 * separate "closed" event, since `Resolved` is already terminal.
 * `evidenceFoundFraudulent` is carried here as an inert observation
 * (ADR-021 §8) — this event triggers no other module's behavior; it is
 * captured by the generic audit sink like every other `AuditableAction`
 * and nothing else.
 */
final class DisputeResolved extends AbstractDomainEvent implements AuditableAction
{
    public function __construct(
        ClockInterface $clock,
        private readonly string $disputeId,
        private readonly DisputeResolutionOutcome $outcome,
        private readonly ?Money $refundAmount,
        private readonly string $resolvedBy,
        private readonly string $resolutionNotes,
        private readonly bool $evidenceFoundFraudulent,
    ) {
        parent::__construct($clock);
    }

    public function eventName(): string
    {
        return 'disputes.dispute_resolved';
    }

    public function payload(): array
    {
        return [
            'dispute_id' => $this->disputeId,
            'outcome' => $this->outcome->value,
            'refund_amount_minor_units' => $this->refundAmount?->minorUnits,
            'refund_amount_currency' => $this->refundAmount !== null ? (string) $this->refundAmount->currency : null,
            'resolved_by' => $this->resolvedBy,
            'resolution_notes' => $this->resolutionNotes,
            'evidence_found_fraudulent' => $this->evidenceFoundFraudulent,
        ];
    }

    public function auditSubjectType(): string
    {
        return 'dispute';
    }

    public function auditSubjectId(): string|int
    {
        return $this->disputeId;
    }

    public function auditPayload(): array
    {
        return $this->payload();
    }
}
