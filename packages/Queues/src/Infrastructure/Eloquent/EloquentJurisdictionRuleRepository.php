<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Infrastructure\Eloquent;

use RowBuddy\Queues\Contracts\JurisdictionRuleRepository;
use RowBuddy\Queues\ValueObjects\JurisdictionRule;

final class EloquentJurisdictionRuleRepository implements JurisdictionRuleRepository
{
    public function findForCountry(string $jurisdictionCountry): array
    {
        return JurisdictionRuleModel::query()
            ->where('jurisdiction_country', $jurisdictionCountry)
            ->get()
            ->map(static fn (JurisdictionRuleModel $model): JurisdictionRule => new JurisdictionRule(
                jurisdictionCountry: $model->jurisdiction_country,
                category: $model->category,
                permitted: $model->permitted,
                effectiveFrom: $model->effective_from->toDateTimeImmutable(),
                effectiveTo: $model->effective_to?->toDateTimeImmutable(),
                active: $model->active,
            ))
            ->values()
            ->all();
    }
}
