<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Infrastructure\Eloquent;

use RowBuddy\Queues\Contracts\RestrictedCategoryRepository;

final class EloquentRestrictedCategoryRepository implements RestrictedCategoryRepository
{
    public function isCategoryRestricted(string $category, string $jurisdictionCountry): bool
    {
        return RestrictedCategoryModel::query()
            ->where('code', $category)
            ->where('active', true)
            ->where(function ($query) use ($jurisdictionCountry) {
                $query->whereNull('jurisdiction_country')
                    ->orWhere('jurisdiction_country', $jurisdictionCountry);
            })
            ->exists();
    }
}
