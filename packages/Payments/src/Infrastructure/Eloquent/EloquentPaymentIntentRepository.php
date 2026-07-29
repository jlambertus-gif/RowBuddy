<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Infrastructure\Eloquent;

use RowBuddy\Payments\Contracts\PaymentIntentRepository;
use RowBuddy\Payments\PaymentIntent;
use RowBuddy\Payments\ValueObjects\PaymentIntentStatus;
use RowBuddy\SharedKernel\ValueObjects\Currency;
use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * Translates between the {@see PaymentIntentModel} Eloquent record and the
 * {@see PaymentIntent} domain aggregate. Unlike Phase 4, `save()` handles
 * both insert and update (ADR-019 §1) — `PaymentIntent` is no longer
 * immutable-after-creation.
 */
final class EloquentPaymentIntentRepository implements PaymentIntentRepository
{
    public function save(PaymentIntent $paymentIntent): void
    {
        PaymentIntentModel::query()->updateOrCreate(
            ['id' => $paymentIntent->id],
            [
                'auction_id' => $paymentIntent->auctionId,
                'winning_bid_id' => $paymentIntent->winningBidId,
                'seller_id' => $paymentIntent->sellerId,
                'buyer_id' => $paymentIntent->buyerId,
                'amount_minor_units' => $paymentIntent->amount->minorUnits,
                'amount_currency' => (string) $paymentIntent->amount->currency,
                'fee_amount_minor_units' => $paymentIntent->feeAmount->minorUnits,
                'stripe_payment_intent_id' => $paymentIntent->stripePaymentIntentId,
                'status' => $paymentIntent->status()->value,
                'decided_at' => $paymentIntent->decidedAt,
            ],
        );
    }

    public function findById(string $id): ?PaymentIntent
    {
        /** @var PaymentIntentModel|null $model */
        $model = PaymentIntentModel::query()->find($id);

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function findByAuctionId(string $auctionId): ?PaymentIntent
    {
        /** @var PaymentIntentModel|null $model */
        $model = PaymentIntentModel::query()->where('auction_id', $auctionId)->first();

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function findByIdForUpdate(string $id): ?PaymentIntent
    {
        /** @var PaymentIntentModel|null $model */
        $model = PaymentIntentModel::query()->lockForUpdate()->find($id);

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function findByStripePaymentIntentId(string $stripePaymentIntentId): ?PaymentIntent
    {
        /** @var PaymentIntentModel|null $model */
        $model = PaymentIntentModel::query()->where('stripe_payment_intent_id', $stripePaymentIntentId)->first();

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    private function toDomain(PaymentIntentModel $model): PaymentIntent
    {
        return PaymentIntent::fromPersistence(
            id: $model->id,
            auctionId: $model->auction_id,
            winningBidId: $model->winning_bid_id,
            sellerId: (string) $model->seller_id,
            buyerId: (string) $model->buyer_id,
            amount: new Money($model->amount_minor_units, new Currency($model->amount_currency)),
            feeAmount: new Money($model->fee_amount_minor_units, new Currency($model->amount_currency)),
            stripePaymentIntentId: $model->stripe_payment_intent_id,
            status: PaymentIntentStatus::from($model->status),
            decidedAt: $model->decided_at->toDateTimeImmutable(),
        );
    }
}
