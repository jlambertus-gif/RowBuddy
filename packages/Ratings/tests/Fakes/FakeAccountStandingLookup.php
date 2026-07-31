<?php

declare(strict_types=1);

namespace RowBuddy\Ratings\Tests\Fakes;

use RowBuddy\Ratings\Contracts\AccountStandingLookup;

final class FakeAccountStandingLookup implements AccountStandingLookup
{
    /** @var array<string, bool> */
    public array $suspended = [];

    public function isSuspended(string $userId): bool
    {
        return $this->suspended[$userId] ?? false;
    }
}
