<?php

declare(strict_types=1);

namespace RowBuddy\Administration\Contracts;

/**
 * Administration's own consumer port onto Queues' jurisdiction-rule
 * write capability (ADR-026 §5/Architecture Refinements §6) — the same
 * "consumer owns the port" discipline as
 * {@see RestrictedCategoryActivationGateway}, for the other of Decision
 * 5's two operational levers. An apps/web adapter bridges to
 * `RowBuddy\Queues\Application\JurisdictionRuleActivationService`.
 * `packages/Administration` never depends on `packages/Queues` directly.
 */
interface JurisdictionRuleActivationGateway
{
    /**
     * @return bool|null null when no rule with this id exists.
     */
    public function findActiveState(string $id): ?bool;

    public function setActive(string $id, bool $active): void;
}
