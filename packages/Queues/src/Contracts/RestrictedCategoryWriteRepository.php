<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Contracts;

/**
 * The narrow, Queues-owned write port for a restricted category's
 * `active` flag only (ADR-026 §5) — deliberately separate from
 * {@see RestrictedCategoryRepository}'s read-only gate check. Exposes no
 * way to create, delete, or edit a category's legal content (`code`,
 * `jurisdiction_country`); only the operational on/off switch.
 */
interface RestrictedCategoryWriteRepository
{
    /**
     * @return bool|null null when no category with this id exists.
     */
    public function findActiveState(string $id): ?bool;

    public function setActive(string $id, bool $active): void;
}
