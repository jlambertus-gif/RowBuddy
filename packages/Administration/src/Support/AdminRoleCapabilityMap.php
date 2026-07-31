<?php

declare(strict_types=1);

namespace RowBuddy\Administration\Support;

use RowBuddy\Administration\ValueObjects\AdminCapability;
use RowBuddy\Administration\ValueObjects\AdminRole;

/**
 * The separate, code-defined map from role to capability set ADR-026's
 * Architecture Refinements §1 requires — kept apart from
 * {@see AdminRole} itself so authorization decisions are never embedded
 * in the role enum. Fixed in code; no runtime permission editing.
 *
 * Both roles currently grant the same, single capability — Sprint 1
 * introduces only `queues.moderate`, so there is nothing yet to
 * differentiate `Moderator` from `Administrator` on. Future sprints
 * (account suspension, dispute administration, audit visibility) are
 * expected to grant additional capabilities to `Administrator` only,
 * which is exactly what this separate map exists to express without
 * touching `AdminRole` itself.
 */
final class AdminRoleCapabilityMap
{
    /**
     * @var array<string, list<AdminCapability>>
     */
    private const MAP = [
        'moderator' => [
            AdminCapability::QueuesModerate,
        ],
        'administrator' => [
            AdminCapability::QueuesModerate,
        ],
    ];

    /**
     * @return list<AdminCapability>
     */
    public function capabilitiesFor(AdminRole $role): array
    {
        return self::MAP[$role->value];
    }

    public function roleGrants(AdminRole $role, AdminCapability $capability): bool
    {
        return in_array($capability, $this->capabilitiesFor($role), true);
    }
}
