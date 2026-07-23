<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Events;

use RowBuddy\SharedKernel\Contracts\AuditableAction;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Events\AbstractDomainEvent;

final class QueuePublished extends AbstractDomainEvent implements AuditableAction
{
    public function __construct(
        ClockInterface $clock,
        private readonly string $queueId,
    ) {
        parent::__construct($clock);
    }

    public function eventName(): string
    {
        return 'queues.queue_published';
    }

    public function payload(): array
    {
        return [
            'queue_id' => $this->queueId,
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
