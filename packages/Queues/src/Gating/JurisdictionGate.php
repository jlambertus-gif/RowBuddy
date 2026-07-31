<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Gating;

use DateTimeImmutable;
use RowBuddy\Queues\ValueObjects\JurisdictionRule;

/**
 * Decides whether the platform may legally operate for a given
 * country/category as of a given instant, from a set of
 * {@see JurisdictionRule} versions. A category-specific rule effective at
 * that instant wins over a country-wide (null-category) one. Fails closed:
 * a country/category that has never been reviewed (no effective rule at
 * all) is NOT permitted — per docs/legal/jurisdiction-requirements.md, a
 * jurisdiction must be explicitly documented and cleared before launch,
 * so "no data" must never be read as "allowed".
 */
final class JurisdictionGate
{
    /**
     * @param  list<JurisdictionRule>  $rules
     */
    public function isPermitted(array $rules, string $category, DateTimeImmutable $asOf): bool
    {
        $effectiveRules = array_values(array_filter(
            $rules,
            static fn (JurisdictionRule $rule): bool => $rule->active && $rule->appliesToCategory($category) && $rule->isEffectiveAt($asOf),
        ));

        $categorySpecific = array_values(array_filter(
            $effectiveRules,
            static fn (JurisdictionRule $rule): bool => $rule->category !== null,
        ));

        if ($categorySpecific !== []) {
            return $categorySpecific[0]->permitted;
        }

        if ($effectiveRules !== []) {
            return $effectiveRules[0]->permitted;
        }

        return false;
    }
}
