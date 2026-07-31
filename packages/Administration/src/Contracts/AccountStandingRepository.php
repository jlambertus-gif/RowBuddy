<?php

declare(strict_types=1);

namespace RowBuddy\Administration\Contracts;

use RowBuddy\Administration\ValueObjects\AccountStandingState;

/**
 * Domain-facing persistence port for current account standing (ADR-026
 * §4). A user with no persisted row has never been suspended and is
 * therefore `Active` by definition — {@see findStanding()} never returns
 * null.
 */
interface AccountStandingRepository
{
    public function findStanding(string $userId): AccountStandingState;

    public function setStanding(string $userId, AccountStandingState $state): void;
}
