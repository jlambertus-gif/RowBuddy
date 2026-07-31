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
 * Sprint 1 introduced only `queues.moderate`, granted to both roles
 * equally, with nothing yet to differentiate `Moderator` from
 * `Administrator`. Sprint 3 (ADR-026 §5) was the first capability
 * granted to `Administrator` only: restricted-category/jurisdiction-
 * rule administration is a legal/compliance-sensitive operational
 * lever, a stronger capability than routine queue moderation. Sprint 4
 * (ADR-026 §3) follows the same reasoning: dispute case/evidence review
 * and administrative corrections are `Administrator`-only as well —
 * exactly the differentiation this separate map exists to express
 * without touching `AdminRole` itself.
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
            AdminCapability::RestrictionsModerate,
            AdminCapability::DisputesReview,
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
