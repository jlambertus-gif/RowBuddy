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
 * `Administrator`. Sprint 3 (ADR-026 §5), Sprint 4 (ADR-026 §3), and
 * Sprint 5 (ADR-026 §6) each granted their new capability to
 * `Administrator` only: restricted-category/jurisdiction-rule
 * administration and dispute review/correction carry legal/financial-
 * correction risk, and — corrected before Sprint 5 committed — even a
 * purely read-only capability like `audit.view` is still sensitive
 * access, since the generic audit log can surface account, payment,
 * dispute, geolocation, and free-text business information. Phase 9
 * Sprint 1 (ADR-027 Decision 5) follows the same reasoning for
 * `horizon.view`: Horizon's own dashboard surfaces job payloads across
 * every queue in the system, a strictly broader (and more
 * operationally sensitive) view than the audit log's own allowlisted
 * one. No Phase 8 or Phase 9 product decision has granted `Moderator`
 * anything beyond the routine queue-moderation capability Sprint 1
 * introduced.
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
            AdminCapability::AuditView,
            AdminCapability::HorizonView,
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
