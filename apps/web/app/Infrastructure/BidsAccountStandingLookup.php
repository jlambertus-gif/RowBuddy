<?php

declare(strict_types=1);

namespace App\Infrastructure;

use RowBuddy\Administration\Contracts\AccountStandingRepository;
use RowBuddy\Administration\ValueObjects\AccountStandingState;
use RowBuddy\Bids\Contracts\AccountStandingLookup;

/**
 * Bridges Bids' read-only {@see AccountStandingLookup} port to
 * Administration's {@see AccountStandingRepository} — the one place
 * allowed to know both packages' internals, per the composition-root
 * pattern every prior cross-module read port in this codebase already
 * uses. `packages/Bids` never depends on Administration directly.
 */
final class BidsAccountStandingLookup implements AccountStandingLookup
{
    public function __construct(
        private readonly AccountStandingRepository $standings,
    ) {}

    public function isSuspended(string $userId): bool
    {
        return $this->standings->findStanding($userId) === AccountStandingState::Suspended;
    }
}
