<?php

declare(strict_types=1);

namespace RowBuddy\Administration\Application;

use RowBuddy\Administration\Contracts\AuditEventLookup;
use RowBuddy\Administration\Support\AuditEventDisplayRegistry;
use RowBuddy\Administration\ValueObjects\AuditEventSnapshot;
use RowBuddy\Administration\ValueObjects\PresentedAuditEvent;

/**
 * Administration's read-only audit-visibility surface (ADR-026 §6): lists
 * recent audit events, presenting each through
 * {@see AuditEventDisplayRegistry}'s allowlist rather than the raw stored
 * payload {@see AuditEventLookup} returns. This service never writes
 * anything — there is no admin action to record for merely viewing the
 * audit log, unlike every other Administration capability so far.
 *
 * Fail-closed (corrected before Sprint 5 committed): an event type with
 * no registered display definition is dropped entirely, not shown as a
 * metadata-only placeholder — {@see listRecent()} silently excludes it
 * and {@see findById()} returns null for it, exactly as if the row did
 * not exist.
 */
final class AuditLogService
{
    public function __construct(
        private readonly AuditEventLookup $events,
        private readonly AuditEventDisplayRegistry $registry,
    ) {}

    /**
     * @return list<PresentedAuditEvent>
     */
    public function listRecent(int $limit = 100): array
    {
        return array_values(array_filter(array_map(
            $this->present(...),
            $this->events->listRecent($limit),
        )));
    }

    public function findById(string $id): ?PresentedAuditEvent
    {
        $event = $this->events->findById($id);

        return $event === null ? null : $this->present($event);
    }

    private function present(AuditEventSnapshot $event): ?PresentedAuditEvent
    {
        if (! $this->registry->isRegistered($event->eventName)) {
            return null;
        }

        return new PresentedAuditEvent(
            id: $event->id,
            eventName: $event->eventName,
            subjectType: $event->subjectType,
            subjectId: $event->subjectId,
            occurredAt: $event->occurredAt,
            fields: $this->registry->filter($event->eventName, $event->payload),
        );
    }
}
