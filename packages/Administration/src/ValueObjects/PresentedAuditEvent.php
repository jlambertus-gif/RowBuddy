<?php

declare(strict_types=1);

namespace RowBuddy\Administration\ValueObjects;

use DateTimeImmutable;
use RowBuddy\Administration\Support\AuditEventDisplayRegistry;

/**
 * The admin-display-safe view of an audit event — only ever constructed
 * for an event type {@see AuditEventDisplayRegistry}
 * recognizes (ADR-026 §6's fail-closed rule, corrected before Sprint 5
 * committed: an unregistered event type produces no
 * `PresentedAuditEvent` at all — not even its id/subject/timestamp —
 * rather than a metadata-only placeholder). `$fields` is always the
 * registry-filtered subset of the original payload, never the raw
 * stored value.
 */
final class PresentedAuditEvent
{
    /**
     * @param  array<string, mixed>  $fields
     */
    public function __construct(
        public readonly string $id,
        public readonly string $eventName,
        public readonly string $subjectType,
        public readonly string $subjectId,
        public readonly DateTimeImmutable $occurredAt,
        public readonly array $fields,
    ) {}
}
