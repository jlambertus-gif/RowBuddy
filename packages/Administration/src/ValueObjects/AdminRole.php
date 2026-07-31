<?php

declare(strict_types=1);

namespace RowBuddy\Administration\ValueObjects;

use RowBuddy\Administration\Support\AdminRoleCapabilityMap;

/**
 * A closed set of administrative roles, fixed in code (ADR-026 §2) —
 * extensible later only by adding a case, never at runtime; no
 * role-management UI exists in the MVP.
 *
 * This enum represents role *identity* only. It deliberately has no
 * method returning capabilities and must never be used for an
 * authorization decision directly (no `$role === AdminRole::Administrator`
 * anywhere application code authorizes an action) — see
 * {@see AdminRoleCapabilityMap} for the
 * separate, code-defined mapping consumed by Laravel Gates/Policies
 * instead.
 */
enum AdminRole: string
{
    case Moderator = 'moderator';
    case Administrator = 'administrator';
}
