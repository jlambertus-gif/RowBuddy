<?php

declare(strict_types=1);

namespace RowBuddy\Administration\Tests\Fakes;

use RowBuddy\Administration\Contracts\AccountStandingRepository;
use RowBuddy\Administration\ValueObjects\AccountStandingState;

final class InMemoryAccountStandingRepository implements AccountStandingRepository
{
    /** @var array<string, AccountStandingState> */
    public array $standings = [];

    public function findStanding(string $userId): AccountStandingState
    {
        return $this->standings[$userId] ?? AccountStandingState::Active;
    }

    public function setStanding(string $userId, AccountStandingState $state): void
    {
        $this->standings[$userId] = $state;
    }
}
