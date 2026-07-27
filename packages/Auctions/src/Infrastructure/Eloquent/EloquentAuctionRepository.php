<?php

declare(strict_types=1);

namespace RowBuddy\Auctions\Infrastructure\Eloquent;

use Illuminate\Database\UniqueConstraintViolationException;
use RowBuddy\Auctions\Auction;
use RowBuddy\Auctions\Contracts\AuctionRepository;
use RowBuddy\Auctions\Exceptions\PresenceSessionAlreadyConsumed;
use RowBuddy\Auctions\ValueObjects\AuctionStatus;
use RowBuddy\SharedKernel\ValueObjects\Currency;
use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * Translates between the {@see AuctionModel} Eloquent record and the
 * {@see Auction} domain aggregate. This is the only place in the Auctions
 * module allowed to know both shapes at once.
 */
final class EloquentAuctionRepository implements AuctionRepository
{
    public function save(Auction $auction): void
    {
        $winningAmount = $auction->winningAmount();

        try {
            AuctionModel::query()->updateOrCreate(
                ['id' => $auction->id],
                [
                    'queue_id' => $auction->queueId,
                    'seller_id' => $auction->sellerId,
                    'presence_session_id' => $auction->presenceSessionId,
                    'starting_price_minor_units' => $auction->startingPrice->minorUnits,
                    'starting_price_currency' => (string) $auction->startingPrice->currency,
                    'opened_at' => $auction->openedAt,
                    'status' => $auction->status()->value,
                    'winning_bid_id' => $auction->winningBidId(),
                    'winning_amount_minor_units' => $winningAmount?->minorUnits,
                    'winning_amount_currency' => $winningAmount !== null ? (string) $winningAmount->currency : null,
                    'proximity_at_risk_since' => $auction->proximityAtRiskSince(),
                ],
            );
        } catch (UniqueConstraintViolationException) {
            throw PresenceSessionAlreadyConsumed::forPresenceSessionId($auction->presenceSessionId);
        }
    }

    public function findById(string $id): ?Auction
    {
        $model = AuctionModel::query()->find($id);

        if (! $model instanceof AuctionModel) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function findByIdForUpdate(string $id): ?Auction
    {
        /** @var AuctionModel|null $model */
        $model = AuctionModel::query()->lockForUpdate()->find($id);

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    private function toDomain(AuctionModel $model): Auction
    {
        $winningAmount = null;
        if ($model->winning_amount_minor_units !== null && $model->winning_amount_currency !== null) {
            $winningAmount = new Money($model->winning_amount_minor_units, new Currency($model->winning_amount_currency));
        }

        return Auction::fromPersistence(
            id: $model->id,
            queueId: $model->queue_id,
            sellerId: (string) $model->seller_id,
            presenceSessionId: $model->presence_session_id,
            startingPrice: new Money($model->starting_price_minor_units, new Currency($model->starting_price_currency)),
            openedAt: $model->opened_at->toDateTimeImmutable(),
            status: AuctionStatus::from($model->status),
            winningBidId: $model->winning_bid_id,
            winningAmount: $winningAmount,
            proximityAtRiskSince: $model->proximity_at_risk_since?->toDateTimeImmutable(),
        );
    }
}
