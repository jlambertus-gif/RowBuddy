<?php

declare(strict_types=1);

namespace App\Infrastructure;

use RowBuddy\Administration\Contracts\JurisdictionRuleActivationGateway;
use RowBuddy\Queues\Application\JurisdictionRuleActivationService;

/**
 * Bridges Administration's {@see JurisdictionRuleActivationGateway} port
 * to Queues' own {@see JurisdictionRuleActivationService} write
 * capability (ADR-026 §5/Architecture Refinements §6) — the one place
 * allowed to know both packages' internals, per the composition-root
 * pattern every prior cross-module port in this codebase already uses.
 * `packages/Administration` never depends on `packages/Queues` directly.
 */
final class JurisdictionRuleActivationAdapter implements JurisdictionRuleActivationGateway
{
    public function __construct(
        private readonly JurisdictionRuleActivationService $jurisdictionRules,
    ) {}

    public function findActiveState(string $id): ?bool
    {
        return $this->jurisdictionRules->findActiveState($id);
    }

    public function setActive(string $id, bool $active): void
    {
        $this->jurisdictionRules->setActive($id, $active);
    }
}
