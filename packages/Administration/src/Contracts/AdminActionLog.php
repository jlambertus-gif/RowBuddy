<?php

declare(strict_types=1);

namespace RowBuddy\Administration\Contracts;

use RowBuddy\Administration\ValueObjects\AccountStandingState;
use RowBuddy\Administration\ValueObjects\AdministrativeActionType;

/**
 * The typed, fail-closed operational record of deliberate administrative
 * decisions (ADR-026 Architecture Refinements §3) — a separate, narrower
 * concern from the platform-wide Audit sink (`AuditableAction`), which
 * remains the one immutable, complete record of every domain event.
 * `admin_actions` never attempts to replace or duplicate it.
 *
 * `$previousState`/`$newState` must be whatever explicit, per-action-type
 * value the caller's own action defines (e.g. an
 * {@see AccountStandingState} value
 * for a suspension/reinstatement) — never an unrestricted serialization
 * of a domain object.
 */
interface AdminActionLog
{
    public function record(
        AdministrativeActionType $type,
        string $adminId,
        string $targetType,
        string $targetId,
        string $reason,
        mixed $previousState,
        mixed $newState,
    ): void;
}
