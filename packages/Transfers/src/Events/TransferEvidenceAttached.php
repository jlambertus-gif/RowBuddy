<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\Events;

use RowBuddy\SharedKernel\Contracts\AuditableAction;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Events\AbstractDomainEvent;
use RowBuddy\Transfers\ValueObjects\TransferEvidenceType;

final class TransferEvidenceAttached extends AbstractDomainEvent implements AuditableAction
{
    public function __construct(
        ClockInterface $clock,
        private readonly string $transferId,
        private readonly TransferEvidenceType $type,
        private readonly string $storageReference,
        private readonly string $submittedBy,
    ) {
        parent::__construct($clock);
    }

    public function eventName(): string
    {
        return 'transfers.transfer_evidence_attached';
    }

    public function payload(): array
    {
        return [
            'transfer_id' => $this->transferId,
            'type' => $this->type->value,
            'storage_reference' => $this->storageReference,
            'submitted_by' => $this->submittedBy,
        ];
    }

    public function auditSubjectType(): string
    {
        return 'transfer';
    }

    public function auditSubjectId(): string|int
    {
        return $this->transferId;
    }

    public function auditPayload(): array
    {
        return $this->payload();
    }
}
