<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Contracts;

use RowBuddy\Queues\ValueObjects\JurisdictionRule;

/**
 * Domain-facing port for jurisdiction-rule lookup
 * (docs/legal/jurisdiction-requirements.md, ADR-005). Read-only, like
 * {@see RestrictedCategoryRepository} — rule authoring is an Administration
 * concern, not something the Queues module writes at runtime.
 */
interface JurisdictionRuleRepository
{
    /**
     * @return list<JurisdictionRule>
     */
    public function findForCountry(string $jurisdictionCountry): array;
}
