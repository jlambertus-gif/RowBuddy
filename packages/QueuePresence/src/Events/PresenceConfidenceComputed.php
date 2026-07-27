<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Events;

use RowBuddy\QueuePresence\Application\ConfidenceRecomputer;
use RowBuddy\SharedKernel\Contracts\AuditableAction;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Events\AbstractDomainEvent;

/**
 * Raised only when a recomputation is materially relevant — the tier
 * actually changed (or this is the first computation to leave
 * Unverified) — never on every recomputation. A confidence score
 * fluctuating within the same tier (e.g. accuracy improving from 45m to
 * 42m) is not audit-worthy; crossing into Location Verified or Evidence
 * Verified is. See {@see ConfidenceRecomputer}.
 */
final class PresenceConfidenceComputed extends AbstractDomainEvent implements AuditableAction
{
    public function __construct(
        ClockInterface $clock,
        private readonly string $presenceSessionId,
        private readonly ?string $previousTier,
        private readonly string $newTier,
        private readonly int $points,
    ) {
        parent::__construct($clock);
    }

    public function eventName(): string
    {
        return 'queue_presence.presence_confidence_computed';
    }

    public function payload(): array
    {
        return [
            'presence_session_id' => $this->presenceSessionId,
            'previous_tier' => $this->previousTier,
            'new_tier' => $this->newTier,
            'points' => $this->points,
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
