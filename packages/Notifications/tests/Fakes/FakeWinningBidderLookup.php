<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Tests\Fakes;

use RowBuddy\Notifications\Contracts\WinningBidderLookup;

final class FakeWinningBidderLookup implements WinningBidderLookup
{
    /** @var array<string, string> */
    public array $bidders = [];

    public function findBidderIdByBidId(string $bidId): ?string
    {
        return $this->bidders[$bidId] ?? null;
    }
}
