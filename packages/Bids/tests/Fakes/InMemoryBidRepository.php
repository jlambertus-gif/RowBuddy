<?php

declare(strict_types=1);

namespace RowBuddy\Bids\Tests\Fakes;

use RowBuddy\Bids\Bid;
use RowBuddy\Bids\Contracts\BidRepository;
use RowBuddy\SharedKernel\ValueObjects\Money;

final class InMemoryBidRepository implements BidRepository
{
    /** @var array<string, Bid> */
    public array $recorded = [];

    public function record(Bid $bid): void
    {
        $this->recorded[$bid->id] = $bid;
    }

    public function highestAmountFor(string $auctionId): ?Money
    {
        $highest = null;

        foreach ($this->recorded as $bid) {
            if ($bid->auctionId !== $auctionId) {
                continue;
            }

            if ($highest === null || $bid->amount->isGreaterThan($highest)) {
                $highest = $bid->amount;
            }
        }

        return $highest;
    }

    public function findById(string $id): ?Bid
    {
        return $this->recorded[$id] ?? null;
    }
}
