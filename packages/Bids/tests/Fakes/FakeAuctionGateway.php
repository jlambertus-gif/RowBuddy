<?php

declare(strict_types=1);

namespace RowBuddy\Bids\Tests\Fakes;

use RowBuddy\Bids\Contracts\AuctionGateway;
use RowBuddy\Bids\ValueObjects\AuctionLockResult;

final class FakeAuctionGateway implements AuctionGateway
{
    /** @var array<string, AuctionLockResult> */
    private array $results = [];

    public function stub(string $auctionId, AuctionLockResult $result): void
    {
        $this->results[$auctionId] = $result;
    }

    public function lockAndCheckForBidding(string $auctionId): AuctionLockResult
    {
        return $this->results[$auctionId] ?? new AuctionLockResult(null, []);
    }
}
