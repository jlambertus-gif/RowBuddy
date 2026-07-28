<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Infrastructure\Eloquent;

use Illuminate\Database\UniqueConstraintViolationException;
use RowBuddy\Payments\Contracts\SellerPayoutAccountRepository;
use RowBuddy\Payments\Exceptions\SellerPayoutAccountAlreadyLinked;
use RowBuddy\Payments\SellerPayoutAccount;

/**
 * Translates between the {@see SellerPayoutAccountModel} Eloquent record
 * and the {@see SellerPayoutAccount} domain aggregate.
 */
final class EloquentSellerPayoutAccountRepository implements SellerPayoutAccountRepository
{
    public function record(SellerPayoutAccount $account): void
    {
        try {
            SellerPayoutAccountModel::query()->create([
                'seller_id' => $account->sellerId,
                'stripe_account_id' => $account->stripeAccountId,
                'linked_at' => $account->linkedAt,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw SellerPayoutAccountAlreadyLinked::forSellerId($account->sellerId);
        }
    }

    public function findBySellerId(string $sellerId): ?SellerPayoutAccount
    {
        /** @var SellerPayoutAccountModel|null $model */
        $model = SellerPayoutAccountModel::query()->find($sellerId);

        if ($model === null) {
            return null;
        }

        return SellerPayoutAccount::fromPersistence(
            sellerId: (string) $model->seller_id,
            stripeAccountId: $model->stripe_account_id,
            linkedAt: $model->linked_at->toDateTimeImmutable(),
        );
    }
}
