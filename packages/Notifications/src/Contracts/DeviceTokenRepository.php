<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Contracts;

use DateTimeImmutable;

/**
 * Domain-facing persistence port for device push-token registration
 * (ADR-028 Decision 6) — owned by `packages/Notifications`, the sole
 * consumer of this data. Deliberately expresses no ORM/storage concept.
 */
interface DeviceTokenRepository
{
    /**
     * Registers a new token, or refreshes `last_seen_at`/`user_id` for
     * an existing one — `expo_push_token` is the natural upsert key
     * (Expo issues one token per physical device+app install), not
     * `(user_id, platform)`, which would collide across two devices of
     * the same platform for the same user.
     */
    public function registerOrRefresh(string $userId, string $platform, string $expoPushToken, DateTimeImmutable $now): void;

    /**
     * Every currently-registered token for a recipient — a user may
     * have more than one device.
     *
     * @return list<string>
     */
    public function findTokensByUserId(string $userId): array;

    /**
     * Called on logout (ADR-028 Decision 6: "a token is removed on
     * logout... never pushed to once its owning session's token has
     * been revoked") — scoped to $userId so a caller can never remove a
     * device token registered to a different user, even one who somehow
     * knows the literal token string (an IDOR the mobile logout body
     * would otherwise open, since expo_push_token values are otherwise
     * unauthenticated). Silently does nothing if the token is unknown,
     * belongs to someone else, or is already removed.
     */
    public function deleteByToken(string $userId, string $expoPushToken): void;
}
