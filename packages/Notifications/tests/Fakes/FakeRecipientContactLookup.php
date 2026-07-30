<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Tests\Fakes;

use RowBuddy\Notifications\Contracts\RecipientContactLookup;

final class FakeRecipientContactLookup implements RecipientContactLookup
{
    /** @var array<string, string> */
    public array $emails = [];

    public function findEmailById(string $recipientId): ?string
    {
        return $this->emails[$recipientId] ?? null;
    }
}
