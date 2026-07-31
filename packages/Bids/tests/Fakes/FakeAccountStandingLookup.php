<?php

declare(strict_types=1);

namespace RowBuddy\Bids\Tests\Fakes;

use RowBuddy\Bids\Contracts\AccountStandingLookup;

final class FakeAccountStandingLookup implements AccountStandingLookup
{
    /** @var array<string, bool> */
    public array $suspended = [];

    public function isSuspended(string $userId): bool
    {
        return $this->suspended[$userId] ?? false;
    }
}
