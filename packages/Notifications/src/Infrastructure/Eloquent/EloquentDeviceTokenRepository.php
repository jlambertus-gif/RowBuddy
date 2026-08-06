<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Infrastructure\Eloquent;

use DateTimeImmutable;
use RowBuddy\Notifications\Contracts\DeviceTokenRepository;

final class EloquentDeviceTokenRepository implements DeviceTokenRepository
{
    public function registerOrRefresh(string $userId, string $platform, string $expoPushToken, DateTimeImmutable $now): void
    {
        DeviceTokenModel::query()->updateOrCreate(
            ['expo_push_token' => $expoPushToken],
            ['user_id' => $userId, 'platform' => $platform, 'last_seen_at' => $now],
        );
    }

    public function findTokensByUserId(string $userId): array
    {
        return DeviceTokenModel::query()
            ->where('user_id', $userId)
            ->pluck('expo_push_token')
            ->all();
    }

    public function deleteByToken(string $userId, string $expoPushToken): void
    {
        DeviceTokenModel::query()
            ->where('user_id', $userId)
            ->where('expo_push_token', $expoPushToken)
            ->delete();
    }
}
