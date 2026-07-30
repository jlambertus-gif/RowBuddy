<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Models\User;
use RowBuddy\Notifications\Contracts\RecipientContactLookup;

/**
 * Bridges Notifications' read-only {@see RecipientContactLookup} port to
 * the `users` table's `email` column — the one place allowed to know
 * both the port and `App\Models\User` at once, per the composition-root
 * pattern every prior cross-module read port in this codebase already
 * uses. `packages/Notifications` never reads `User` directly.
 */
final class EloquentRecipientContactLookup implements RecipientContactLookup
{
    public function findEmailById(string $recipientId): ?string
    {
        /** @var User|null $user */
        $user = User::query()->find($recipientId);

        return $user?->email;
    }
}
