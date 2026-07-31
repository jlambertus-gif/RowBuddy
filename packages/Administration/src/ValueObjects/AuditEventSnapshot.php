<?php

declare(strict_types=1);

namespace RowBuddy\Administration\ValueObjects;

use DateTimeImmutable;
use RowBuddy\Administration\Application\AuditLogService;

/**
 * A raw row from the platform-wide audit sink (`audit_events`), exactly
 * as stored — the complete, unfiltered payload (ADR-026 §6: "the audit
 * store remains the system of record and continues to retain each
 * event's complete payload unchanged"). This is Administration's own
 * read-side copy of that data via {@see AuditEventLookup}; filtering
 * down to what an administrator may actually see happens one layer up,
 * in {@see AuditLogService}, never
 * here.
 */
final class AuditEventSnapshot
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $id,
        public readonly string $eventName,
        public readonly string $subjectType,
        public readonly string $subjectId,
        public readonly array $payload,
        public readonly DateTimeImmutable $occurredAt,
    ) {}
}
