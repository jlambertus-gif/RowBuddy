<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Tests\Fakes;

use RowBuddy\Queues\Contracts\JurisdictionRuleRepository;
use RowBuddy\Queues\ValueObjects\JurisdictionRule;

final class InMemoryJurisdictionRuleRepository implements JurisdictionRuleRepository
{
    /** @var list<JurisdictionRule> */
    private array $rules = [];

    public function addRule(JurisdictionRule $rule): void
    {
        $this->rules[] = $rule;
    }

    public function findForCountry(string $jurisdictionCountry): array
    {
        return array_values(array_filter(
            $this->rules,
            static fn (JurisdictionRule $rule): bool => $rule->jurisdictionCountry === $jurisdictionCountry,
        ));
    }
}
