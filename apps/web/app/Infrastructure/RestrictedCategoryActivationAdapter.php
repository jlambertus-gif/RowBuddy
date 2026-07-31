<?php

declare(strict_types=1);

namespace App\Infrastructure;

use RowBuddy\Administration\Contracts\RestrictedCategoryActivationGateway;
use RowBuddy\Queues\Application\RestrictedCategoryActivationService;

/**
 * Bridges Administration's {@see RestrictedCategoryActivationGateway}
 * port to Queues' own {@see RestrictedCategoryActivationService} write
 * capability (ADR-026 §5/Architecture Refinements §6) — the one place
 * allowed to know both packages' internals, per the composition-root
 * pattern every prior cross-module port in this codebase already uses.
 * `packages/Administration` never depends on `packages/Queues` directly.
 */
final class RestrictedCategoryActivationAdapter implements RestrictedCategoryActivationGateway
{
    public function __construct(
        private readonly RestrictedCategoryActivationService $categories,
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
