<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Events;

use RowBuddy\SharedKernel\Contracts\AuditableAction;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Events\AbstractDomainEvent;

final class PresenceSessionEnded extends AbstractDomainEvent implements AuditableAction
{
    public function __construct(
        ClockInterface $clock,
        private readonly string $presenceSessionId,
    ) {
        parent::__construct($clock);
    }

    public function eventName(): string
    {
        return 'queue_presence.presence_session_ended';
    }

    public function payload(): array
    {
        return [
            'presence_session_id' => $this->presenceSessionId,
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
