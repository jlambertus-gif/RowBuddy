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
        return $this->findHighestBidFor($auctionId)?->amount;
    }

    public function findHighestBidFor(string $auctionId): ?Bid
    {
        $bids = array_values(array_filter(
            $this->recorded,
            static fn (Bid $bid): bool => $bid->auctionId === $auctionId,
        ));

        if ($bids === []) {
            return null;
        }

        usort($bids, static function (Bid $a, Bid $b): int {
            if (! $a->amount->equals($b->amount)) {
                return $a->amount->isGreaterThan($b->amount) ? -1 : 1;
            }

            if ($a->placedAt != $b->placedAt) {
                return $a->placedAt < $b->placedAt ? -1 : 1;
            }

            return $a->id <=> $b->id;
        });

        return $bids[0];
    }

    public function findById(string $id): ?Bid
    {
        return $this->recorded[$id] ?? null;
    }

    public function countFor(string $auctionId): int
    {
        return count(array_filter(
            $this->recorded,
            static fn (Bid $bid): bool => $bid->auctionId === $auctionId,
        ));
    }
}
