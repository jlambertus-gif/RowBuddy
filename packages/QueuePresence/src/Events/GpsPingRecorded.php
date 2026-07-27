<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Events;

use RowBuddy\SharedKernel\Contracts\AuditableAction;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Events\AbstractDomainEvent;

/**
 * Deliberately not an {@see AuditableAction}:
 * GPS pings are a high-frequency signal (recorded repeatedly through a
 * single presence session), unlike the once-per-lifecycle-step events this
 * module also raises. Auditing every ping would dominate the audit_events
 * table for no evidentiary benefit — the session's start/end and its
 * evidence already give an auditor the meaningful checkpoints. Individual
 * pings remain fully queryable via `presence_signals` (Sprint 2) for the
 * confidence-scoring engine (Sprint 3); they are simply not duplicated into
 * the audit trail.
 */
final class GpsPingRecorded extends AbstractDomainEvent
{
    public function __construct(
        ClockInterface $clock,
        private readonly string $presenceSessionId,
        private readonly float $latitude,
        private readonly float $longitude,
        private readonly float $accuracyInMeters,
    ) {
        parent::__construct($clock);
    }

    public function eventName(): string
    {
        return 'queue_presence.gps_ping_recorded';
    }

    public function payload(): array
    {
        return [
            'presence_session_id' => $this->presenceSessionId,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'accuracy_in_meters' => $this->accuracyInMeters,
        ];
    }
}
