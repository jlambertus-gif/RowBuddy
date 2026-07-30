<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Contracts;

use RowBuddy\Notifications\ValueObjects\RecipientLocalePreferenceSnapshot;

/**
 * Notifications-owned read port into Identity's `users` table (Phase 7
 * Notifications sprint plan) — extending the "consumer owns the port"
 * pattern to Identity: `packages/Notifications` reads a recipient's
 * stored locale preference read-only and gains no dependency on
 * Identity's internals. Notifications must never read the `User` model
 * directly; this port, implemented by an `apps/web` adapter, is the only
 * path.
 */
interface RecipientLocalePreferenceLookup
{
    public function findByRecipientId(string $recipientId): ?RecipientLocalePreferenceSnapshot;
}
