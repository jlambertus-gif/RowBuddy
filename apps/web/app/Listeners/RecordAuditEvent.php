<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\AuditEvent;
use Illuminate\Support\Str;
use RowBuddy\SharedKernel\Contracts\AuditableAction;
use RowBuddy\SharedKernel\Contracts\DomainEvent;

/**
 * The one generic audit listener for the whole platform (Sprint 6):
 * registered against the AuditableAction *interface* in
 * AppServiceProvider, not any module's concrete event class, so it fires
 * for any bounded context's audit-worthy domain event automatically —
 * Laravel's dispatcher resolves listeners for the interfaces an event
 * implements, not just its own class. No per-module wiring needed as new
 * modules add their own AuditableAction events.
 *
 * Every event implementing AuditableAction in this codebase also
 * implements DomainEvent (via AbstractDomainEvent), which is where
 * eventName()/occurredAt() come from.
 */
final class RecordAuditEvent
{
    public function handle(AuditableAction&DomainEvent $event): void
    {
        AuditEvent::query()->create([
            'id' => (string) Str::uuid(),
            'event_name' => $event->eventName(),
            'subject_type' => $event->auditSubjectType(),
            'subject_id' => (string) $event->auditSubjectId(),
            'payload' => $event->auditPayload(),
            'occurred_at' => $event->occurredAt(),
        ]);
    }
}
