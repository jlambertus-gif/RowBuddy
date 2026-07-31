<?php

declare(strict_types=1);

namespace RowBuddy\Administration\Infrastructure\Eloquent;

use RowBuddy\Administration\Contracts\AccountStandingRepository;
use RowBuddy\Administration\ValueObjects\AccountStandingState;

final class EloquentAccountStandingRepository implements AccountStandingRepository
{
    public function findStanding(string $userId): AccountStandingState
    {
        /** @var AccountStandingModel|null $model */
        $model = AccountStandingModel::query()->find($userId);

        if ($model === null) {
            return AccountStandingState::Active;
        }

        return AccountStandingState::from($model->state);
    }

    public function setStanding(string $userId, AccountStandingState $state): void
    {
        AccountStandingModel::query()->updateOrCreate(
            ['user_id' => $userId],
            ['state' => $state->value],
        );
    }
}
