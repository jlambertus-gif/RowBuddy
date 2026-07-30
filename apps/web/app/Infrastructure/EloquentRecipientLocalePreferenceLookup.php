<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Models\User;
use RowBuddy\Notifications\Contracts\RecipientLocalePreferenceLookup;
use RowBuddy\Notifications\ValueObjects\RecipientLocalePreferenceSnapshot;

/**
 * Bridges Notifications' read-only {@see RecipientLocalePreferenceLookup}
 * port to the `users` table's minimal locale preference columns — the
 * one place allowed to know both the port and `App\Models\User` at once,
 * per the composition-root pattern every prior cross-module read port in
 * this codebase already uses. `packages/Notifications` never reads
 * `User` directly.
 */
final class EloquentRecipientLocalePreferenceLookup implements RecipientLocalePreferenceLookup
{
    public function findByRecipientId(string $recipientId): ?RecipientLocalePreferenceSnapshot
    {
        /** @var User|null $user */
        $user = User::query()->find($recipientId);

        if ($user === null) {
            return null;
        }

        return new RecipientLocalePreferenceSnapshot(
            language: $user->language,
            countryCode: $user->country_code,
            currencyCode: $user->currency,
            timezone: $user->timezone,
        );
    }
}
