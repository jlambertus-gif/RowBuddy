<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Models\AuditEvent;
use RowBuddy\Administration\Contracts\AuditEventLookup;
use RowBuddy\Administration\ValueObjects\AuditEventSnapshot;

/**
 * Bridges Administration's read-only {@see AuditEventLookup} port to the
 * platform-wide audit sink's own {@see AuditEvent} model — the one place
 * allowed to know both at once, per the composition-root pattern every
 * prior cross-module read port in this codebase already uses. There is
 * no separate Audit package: the sink lives in apps/web itself
 * (App\Listeners\RecordAuditEvent), so this adapter queries it directly
 * rather than through an intermediate domain repository.
 */
final class EloquentAuditEventLookup implements AuditEventLookup
{
    public function listRecent(int $limit): array
    {
        return AuditEvent::query()
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get()
            ->map($this->toSnapshot(...))
            ->all();
    }

    public function findById(string $id): ?AuditEventSnapshot
    {
        $event = AuditEvent::query()->find($id);

        return $event === null ? null : $this->toSnapshot($event);
    }

    private function toSnapshot(AuditEvent $event): AuditEventSnapshot
    {
        return new AuditEventSnapshot(
            id: $event->id,
            eventName: $event->event_name,
            subjectType: $event->subject_type,
            subjectId: $event->subject_id,
            payload: $event->payload,
            occurredAt: $event->occurred_at->toDateTimeImmutable(),
        );
    }
}
