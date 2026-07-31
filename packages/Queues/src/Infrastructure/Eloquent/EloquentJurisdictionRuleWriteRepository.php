<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Infrastructure\Eloquent;

use RowBuddy\Queues\Contracts\JurisdictionRuleWriteRepository;

final class EloquentJurisdictionRuleWriteRepository implements JurisdictionRuleWriteRepository
{
    public function findActiveState(string $id): ?bool
    {
        $model = JurisdictionRuleModel::query()->find($id);

        return $model?->active;
    }

    public function setActive(string $id, bool $active): void
    {
        JurisdictionRuleModel::query()->where('id', $id)->update(['active' => $active]);
    }
}
