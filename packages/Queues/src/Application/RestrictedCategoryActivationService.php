<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Application;

use RowBuddy\Queues\Contracts\RestrictedCategoryWriteRepository;

/**
 * The Queues-owned write capability ADR-026 §5/Architecture Refinements
 * §6 requires: Administration may only toggle a restricted category's
 * `active` flag through this service, never by writing to
 * `restricted_categories` directly. Queues remains the sole owner of the
 * row and its invariants — this service exposes nothing beyond the one
 * operational switch Decision 5 grants an administrator.
 */
final class RestrictedCategoryActivationService
{
    public function __construct(
        private readonly RestrictedCategoryWriteRepository $categories,
    ) {}

    public function findActiveState(string $id): ?bool
    {
        return $this->categories->findActiveState($id);
    }

    public function setActive(string $id, bool $active): void
    {
        $this->categories->setActive($id, $active);
    }
}
