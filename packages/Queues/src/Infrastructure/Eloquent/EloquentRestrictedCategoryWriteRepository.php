<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Infrastructure\Eloquent;

use RowBuddy\Queues\Contracts\RestrictedCategoryWriteRepository;

final class EloquentRestrictedCategoryWriteRepository implements RestrictedCategoryWriteRepository
{
    public function findActiveState(string $id): ?bool
    {
        $model = RestrictedCategoryModel::query()->find($id);

        return $model?->active;
    }

    public function setActive(string $id, bool $active): void
    {
        RestrictedCategoryModel::query()->where('id', $id)->update(['active' => $active]);
    }
}
