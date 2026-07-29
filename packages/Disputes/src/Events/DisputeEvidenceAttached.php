<?php

declare(strict_types=1);

namespace RowBuddy\Disputes\Events;

use RowBuddy\Disputes\ValueObjects\DisputeEvidenceType;
use RowBuddy\SharedKernel\Contracts\AuditableAction;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Events\AbstractDomainEvent;

final class DisputeEvidenceAttached extends AbstractDomainEvent implements AuditableAction
{
    public function __construct(
        ClockInterface $clock,
        private readonly string $disputeId,
        private readonly DisputeEvidenceType $type,
        private readonly string $storageReference,
        private readonly string $submittedBy,
    ) {
        parent::__construct($clock);
    }

    public function eventName(): string
    {
        return 'disputes.dispute_evidence_attached';
    }

    public function payload(): array
    {
        return [
            'dispute_id' => $this->disputeId,
            'type' => $this->type->value,
            'storage_reference' => $this->storageReference,
            'submitted_by' => $this->submittedBy,
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
