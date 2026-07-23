<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Contracts;

/**
 * Domain-facing port for the restricted-category gate
 * (docs/legal/restricted-queues.md, ADR-005). Deliberately a read-only
 * policy check, not a full CRUD repository — nothing in the Queues module
 * needs to create or edit restricted-category rows at runtime, only ask
 * "is this category currently restricted for this jurisdiction".
 */
interface RestrictedCategoryRepository
{
    public function isCategoryRestricted(string $category, string $jurisdictionCountry): bool;
}
