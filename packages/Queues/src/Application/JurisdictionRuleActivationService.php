<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Application;

use RowBuddy\Queues\Contracts\JurisdictionRuleWriteRepository;

/**
 * The Queues-owned write capability ADR-026 §5/Architecture Refinements
 * §6 requires: Administration may only toggle a jurisdiction rule's
 * `active` flag through this service, never by writing to
 * `jurisdiction_rules` directly. Queues remains the sole owner of the
 * row and its invariants — this service exposes nothing beyond the one
 * operational switch Decision 5 grants an administrator; the rule's
 * legal content (`permitted`, `jurisdiction_country`, `category`,
 * `effective_from`/`effective_to`) is never reachable through it.
 */
final class JurisdictionRuleActivationService
{
    public function __construct(
        private readonly JurisdictionRuleWriteRepository $rules,
    ) {}

    public function findActiveState(string $id): ?bool
    {
        return $this->rules->findActiveState($id);
    }

    public function setActive(string $id, bool $active): void
    {
        $this->rules->setActive($id, $active);
    }
}
