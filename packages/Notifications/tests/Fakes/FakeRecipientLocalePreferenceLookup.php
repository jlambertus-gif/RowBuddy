<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Tests\Fakes;

use RowBuddy\Notifications\Contracts\RecipientLocalePreferenceLookup;
use RowBuddy\Notifications\ValueObjects\RecipientLocalePreferenceSnapshot;

final class FakeRecipientLocalePreferenceLookup implements RecipientLocalePreferenceLookup
{
    /** @var array<string, RecipientLocalePreferenceSnapshot> */
    public array $snapshots = [];

    public function findByRecipientId(string $recipientId): ?RecipientLocalePreferenceSnapshot
    {
        return $this->snapshots[$recipientId] ?? null;
    }
}
