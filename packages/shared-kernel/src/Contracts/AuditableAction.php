<?php

declare(strict_types=1);

namespace RowBuddy\SharedKernel\Contracts;

/**
 * Implemented by domain events/actions that must produce an audit-trail
 * entry. The Audit context listens generically for anything implementing
 * this contract instead of every module hand-writing audit-log calls.
 */
interface AuditableAction
{
    public function auditSubjectType(): string;

    public function auditSubjectId(): string|int;

    /**
     * @return array<string, mixed>
     */
    public function auditPayload(): array;
}
