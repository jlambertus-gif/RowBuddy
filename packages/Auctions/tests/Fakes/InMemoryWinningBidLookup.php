<?php

declare(strict_types=1);

namespace RowBuddy\Auctions\Tests\Fakes;

use RowBuddy\Auctions\Contracts\WinningBidLookup;
use RowBuddy\Auctions\ValueObjects\WinningBidCandidate;

final class InMemoryWinningBidLookup implements WinningBidLookup
{
    /** @var array<string, WinningBidCandidate> */
    private array $candidates = [];

    public function stub(string $auctionId, WinningBidCandidate $candidate): void
    {
        $this->candidates[$auctionId] = $candidate;
    }

    public function highestBidFor(string $auctionId): ?WinningBidCandidate
    {
        return $this->candidates[$auctionId] ?? null;
    }
}
