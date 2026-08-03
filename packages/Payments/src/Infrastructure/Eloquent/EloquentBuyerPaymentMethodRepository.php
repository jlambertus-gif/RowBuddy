<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Infrastructure\Eloquent;

use RowBuddy\Payments\BuyerPaymentMethod;
use RowBuddy\Payments\Contracts\BuyerPaymentMethodRepository;

/**
 * Translates between the {@see BuyerPaymentMethodModel} Eloquent record
 * and the {@see BuyerPaymentMethod} domain entity. `save()` is a real
 * upsert (`updateOrCreate`), unlike {@see EloquentSellerPayoutAccountRepository::record()} —
 * a buyer replacing their saved payment method is expected, ordinary
 * behavior, not a conflict.
 */
final class EloquentBuyerPaymentMethodRepository implements BuyerPaymentMethodRepository
{
    public function save(BuyerPaymentMethod $method): void
    {
        BuyerPaymentMethodModel::query()->updateOrCreate(
            ['buyer_id' => $method->buyerId],
            [
                'stripe_customer_id' => $method->stripeCustomerId,
                'stripe_payment_method_id' => $method->stripePaymentMethodId,
                'saved_at' => $method->savedAt,
            ],
        );
    }

    public function findByBuyerId(string $buyerId): ?BuyerPaymentMethod
    {
        /** @var BuyerPaymentMethodModel|null $model */
        $model = BuyerPaymentMethodModel::query()->find($buyerId);

        if ($model === null) {
            return null;
        }

        return BuyerPaymentMethod::fromPersistence(
            buyerId: (string) $model->buyer_id,
            stripeCustomerId: $model->stripe_customer_id,
            stripePaymentMethodId: $model->stripe_payment_method_id,
            savedAt: $model->saved_at->toDateTimeImmutable(),
        );
    }
}
