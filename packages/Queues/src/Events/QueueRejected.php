<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Events;

use RowBuddy\SharedKernel\Contracts\AuditableAction;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Events\AbstractDomainEvent;

final class QueueRejected extends AbstractDomainEvent implements AuditableAction
{
    public function __construct(
        ClockInterface $clock,
        private readonly string $queueId,
        private readonly string $rejectedByUserId,
        private readonly string $reason,
    ) {
        parent::__construct($clock);
    }

    public function eventName(): string
    {
        return 'queues.queue_rejected';
    }

    public function payload(): array
    {
        return [
            'queue_id' => $this->queueId,
            'rejected_by_user_id' => $this->rejectedByUserId,
            'reason' => $this->reason,
        ];
    }

    public function auditSubjectType(): string
    {
        return 'queue';
    }

    public function auditSubjectId(): string|int
    {
        return $this->queueId;
    }

    public function auditPayload(): array
    {
        return $this->payload();
    }
}
