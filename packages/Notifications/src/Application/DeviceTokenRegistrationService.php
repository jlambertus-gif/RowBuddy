<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Application;

use RowBuddy\Notifications\Contracts\DeviceTokenRepository;
use RowBuddy\SharedKernel\Contracts\ClockInterface;

/**
 * Orchestrates device push-token registration (ADR-028 Decision 6).
 * Deliberately minimal — no business rule beyond "this token now belongs
 * to this user, as of now" — but still goes through an explicit
 * application service rather than a controller calling the repository
 * directly, the same discipline every other state change in this
 * codebase follows (mirrors PresenceSessionService's own posture for a
 * similarly simple write).
 */
final class DeviceTokenRegistrationService
{
    public function __construct(
        private readonly DeviceTokenRepository $deviceTokens,
        private readonly ClockInterface $clock,
    ) {}

    public function register(string $userId, string $platform, string $expoPushToken): void
    {
        $this->deviceTokens->registerOrRefresh($userId, $platform, $expoPushToken, $this->clock->now());
    }
}
