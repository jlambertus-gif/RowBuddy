<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use RowBuddy\Administration\ValueObjects\PresentedAuditEvent;

/**
 * Shared PresentedAuditEvent -> JSON shape for the audit-log controller
 * — no business logic, just serialization. `fields` is already the
 * allowlist-filtered subset AuditLogService produced; this trait never
 * touches the raw stored payload.
 */
trait RendersAuditLogResponses
{
    /**
     * @return array<string, mixed>
     */
    private function toResponse(PresentedAuditEvent $event): array
    {
        return [
            'id' => $event->id,
            'event_name' => $event->eventName,
            'subject_type' => $event->subjectType,
            'subject_id' => $event->subjectId,
            'occurred_at' => $event->occurredAt->format(DATE_ATOM),
            'fields' => $event->fields,
        ];
    }
}
