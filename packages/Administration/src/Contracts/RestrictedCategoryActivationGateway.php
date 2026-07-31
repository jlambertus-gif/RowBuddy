<?php

declare(strict_types=1);

namespace RowBuddy\Administration\Contracts;

/**
 * Administration's own consumer port onto Queues' restricted-category
 * write capability (ADR-026 §5/Architecture Refinements §6) — mirrors
 * the "consumer owns the port" discipline already proven across this
 * codebase (e.g. Ratings'/Notifications' own copies of
 * TransferParticipantLookup), just in the write direction: Administration
 * declares what it needs, an apps/web adapter bridges to
 * `RowBuddy\Queues\Application\RestrictedCategoryActivationService`.
 * `packages/Administration` never depends on `packages/Queues` directly.
 */
interface RestrictedCategoryActivationGateway
{
    /**
     * @return bool|null null when no category with this id exists.
     */
    public function findActiveState(string $id): ?bool;

    public function setActive(string $id, bool $active): void;
}
