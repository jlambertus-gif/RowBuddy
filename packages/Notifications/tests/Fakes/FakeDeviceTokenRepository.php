<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Tests\Fakes;

use DateTimeImmutable;
use RowBuddy\Notifications\Contracts\DeviceTokenRepository;

final class FakeDeviceTokenRepository implements DeviceTokenRepository
{
    /** @var array<string, list<string>> */
    public array $tokensByUserId = [];

    public function registerOrRefresh(string $userId, string $platform, string $expoPushToken, DateTimeImmutable $now): void
    {
        $this->tokensByUserId[$userId][] = $expoPushToken;
    }

    public function findTokensByUserId(string $userId): array
    {
        return $this->tokensByUserId[$userId] ?? [];
    }

    public function deleteByToken(string $userId, string $expoPushToken): void
    {
        $this->tokensByUserId[$userId] = array_values(
            array_diff($this->tokensByUserId[$userId] ?? [], [$expoPushToken]),
        );
    }
}
