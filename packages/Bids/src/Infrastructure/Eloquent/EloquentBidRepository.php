<?php

declare(strict_types=1);

namespace RowBuddy\Bids\Infrastructure\Eloquent;

use RowBuddy\Bids\Bid;
use RowBuddy\Bids\Contracts\BidRepository;
use RowBuddy\SharedKernel\ValueObjects\Currency;
use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * Translates between the {@see BidModel} Eloquent record and the
 * {@see Bid} domain aggregate. Deliberately has no update/delete method —
 * only `record()` (insert) and reads, mirroring {@see BidRepository}'s
 * own append-only contract.
 */
final class EloquentBidRepository implements BidRepository
{
    public function record(Bid $bid): void
    {
        BidModel::query()->create([
            'id' => $bid->id,
            'auction_id' => $bid->auctionId,
            'bidder_id' => $bid->bidderId,
            'amount_minor_units' => $bid->amount->minorUnits,
            'amount_currency' => (string) $bid->amount->currency,
            'placed_at' => $bid->placedAt,
        ]);
    }

    public function highestAmountFor(string $auctionId): ?Money
    {
        return $this->findHighestBidFor($auctionId)?->amount;
    }

    public function findHighestBidFor(string $auctionId): ?Bid
    {
        /** @var BidModel|null $model */
        $model = BidModel::query()
            ->where('auction_id', $auctionId)
            ->orderByDesc('amount_minor_units')
            ->orderBy('placed_at')
            ->orderBy('id')
            ->first();

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function findById(string $id): ?Bid
    {
        /** @var BidModel|null $model */
        $model = BidModel::query()->find($id);

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    private function toDomain(BidModel $model): Bid
    {
        return Bid::fromPersistence(
            id: $model->id,
            auctionId: $model->auction_id,
            bidderId: (string) $model->bidder_id,
            amount: new Money($model->amount_minor_units, new Currency($model->amount_currency)),
            placedAt: $model->placed_at->toDateTimeImmutable(),
        );
    }
}
