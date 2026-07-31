<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Contracts;

/**
 * The narrow, Queues-owned write port for a jurisdiction rule's `active`
 * flag only (ADR-026 §5/Architecture Refinements §6) — deliberately
 * separate from {@see JurisdictionRuleRepository}'s read-only lookup.
 * Exposes no way to create a rule or edit its legal content
 * (`permitted`, `jurisdiction_country`, `category`, `effective_from`/
 * `effective_to`); only the operational on/off switch.
 */
interface JurisdictionRuleWriteRepository
{
    /**
     * @return bool|null null when no rule with this id exists.
     */
    public function findActiveState(string $id): ?bool;

    public function setActive(string $id, bool $active): void;
}
