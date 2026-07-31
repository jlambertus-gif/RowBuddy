<?php

declare(strict_types=1);

namespace RowBuddy\Administration\Contracts;

use RowBuddy\Administration\ValueObjects\AdminRole;

/**
 * Domain-facing persistence port for role assignment (ADR-026 §2). At
 * most one role per user for MVP — `assignRole()` sets or replaces the
 * user's current assignment, it never adds a second concurrent role.
 */
interface AdminRoleAssignmentRepository
{
    public function findRoleForUser(string $userId): ?AdminRole;

    public function assignRole(string $userId, AdminRole $role, ?string $assignedBy): void;
}
