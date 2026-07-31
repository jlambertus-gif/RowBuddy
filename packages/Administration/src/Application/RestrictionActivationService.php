<?php

declare(strict_types=1);

namespace RowBuddy\Administration\Application;

use RowBuddy\Administration\Contracts\AdminActionLog;
use RowBuddy\Administration\Contracts\JurisdictionRuleActivationGateway;
use RowBuddy\Administration\Contracts\RestrictedCategoryActivationGateway;
use RowBuddy\Administration\Exceptions\AdministrativeActionReasonRequired;
use RowBuddy\Administration\Exceptions\AdministrativeTargetNotFound;
use RowBuddy\Administration\Infrastructure\AdministrationServiceProvider;
use RowBuddy\Administration\ValueObjects\AdminCapability;
use RowBuddy\Administration\ValueObjects\AdministrativeActionType;

/**
 * Orchestrates the sole operational lever ADR-026 §5 grants an
 * administrator over restricted categories and jurisdiction rules:
 * activating or deactivating an *existing* row. Every legal
 * determination — a category's code/jurisdiction, a rule's `permitted`/
 * `effective_from`/`effective_to` — stays exclusively owned by
 * `packages/Queues`; this service reaches it only through
 * {@see RestrictedCategoryActivationGateway}/
 * {@see JurisdictionRuleActivationGateway}, the narrow write capabilities
 * Queues itself exposes, never by writing to Queues' tables directly.
 *
 * Mirrors {@see AccountSuspensionService}'s (Sprint 2) authorization
 * posture exactly: this service assumes the caller is already
 * authorized and enforces only business invariants (mandatory reason,
 * target existence, current state). Capability authorization — the
 * `restrictions.moderate` Gate {@see AdministrationServiceProvider}
 * already registers generically for every {@see AdminCapability}
 * case — belongs at the application boundary (console command, HTTP
 * controller, or equivalent composition-root entry point) that
 * eventually calls this service, never inside it.
 */
final class RestrictionActivationService
{
    public function __construct(
        private readonly RestrictedCategoryActivationGateway $categories,
        private readonly JurisdictionRuleActivationGateway $jurisdictionRules,
        private readonly AdminActionLog $actions,
    ) {}

    /**
     * @throws AdministrativeActionReasonRequired
     * @throws AdministrativeTargetNotFound
     */
    public function setRestrictedCategoryActive(string $categoryId, string $adminId, bool $active, string $reason): void
    {
        $this->assertReasonNotBlank($reason);

        $previous = $this->categories->findActiveState($categoryId);

        if ($previous === null) {
            throw AdministrativeTargetNotFound::forTarget('restricted_category', $categoryId);
        }

        $this->categories->setActive($categoryId, $active);

        $this->actions->record(
            $active ? AdministrativeActionType::RestrictedCategoryActivated : AdministrativeActionType::RestrictedCategoryDeactivated,
            $adminId,
            'restricted_category',
            $categoryId,
            $reason,
            $previous,
            $active,
        );
    }

    /**
     * @throws AdministrativeActionReasonRequired
     * @throws AdministrativeTargetNotFound
     */
    public function setJurisdictionRuleActive(string $ruleId, string $adminId, bool $active, string $reason): void
    {
        $this->assertReasonNotBlank($reason);

        $previous = $this->jurisdictionRules->findActiveState($ruleId);

        if ($previous === null) {
            throw AdministrativeTargetNotFound::forTarget('jurisdiction_rule', $ruleId);
        }

        $this->jurisdictionRules->setActive($ruleId, $active);

        $this->actions->record(
            $active ? AdministrativeActionType::JurisdictionRuleActivated : AdministrativeActionType::JurisdictionRuleDeactivated,
            $adminId,
            'jurisdiction_rule',
            $ruleId,
            $reason,
            $previous,
            $active,
        );
    }

    /**
     * @throws AdministrativeActionReasonRequired
     */
    private function assertReasonNotBlank(string $reason): void
    {
        if (trim($reason) === '') {
            throw AdministrativeActionReasonRequired::create();
        }
    }
}
