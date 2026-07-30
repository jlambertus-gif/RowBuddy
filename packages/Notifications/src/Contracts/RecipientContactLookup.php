<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Contracts;

/**
 * Notifications-owned read port into Identity's `users` table, separate
 * from {@see RecipientLocalePreferenceLookup} — email address is a
 * distinct concern from locale preference, mirroring this codebase's
 * standing discipline of one narrow, purpose-built port per concern
 * (e.g. `TransferParticipantLookup` vs. `TransferCaseLookup`) rather than
 * one combined "everything about this user" port. The sole delivery
 * channel for Phase 7 (ADR-025 §1) needs a real email address to send
 * to; this is the only path Notifications may use to obtain one.
 */
interface RecipientContactLookup
{
    public function findEmailById(string $recipientId): ?string;
}
