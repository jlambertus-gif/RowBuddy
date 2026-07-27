<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Events;

use RowBuddy\SharedKernel\Contracts\AuditableAction;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Events\AbstractDomainEvent;

final class PresenceSessionStarted extends AbstractDomainEvent implements AuditableAction
{
    public function __construct(
        ClockInterface $clock,
        private readonly string $presenceSessionId,
        private readonly string $queueId,
        private readonly string $sellerId,
    ) {
        parent::__construct($clock);
    }

    public function eventName(): string
    {
        return 'queue_presence.presence_session_started';
    }

    public function payload(): array
    {
        return [
            'presence_session_id' => $this->presenceSessionId,
            'queue_id' => $this->queueId,
            'seller_id' => $this->sellerId,
        ];
    }

    public function auditSubjectType(): string
    {
        return 'presence_session';
    }

    public function auditSubjectId(): string|int
    {
        return $this->presenceSessionId;
    }

    public function auditPayload(): array
    {
        return $this->payload();
    }
}
