<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Tests\Fakes;

use RowBuddy\Payments\Contracts\SellerPayoutAccountRepository;
use RowBuddy\Payments\Exceptions\SellerPayoutAccountAlreadyLinked;
use RowBuddy\Payments\SellerPayoutAccount;

final class InMemorySellerPayoutAccountRepository implements SellerPayoutAccountRepository
{
    /** @var array<string, SellerPayoutAccount> */
    public array $recorded = [];

    public function record(SellerPayoutAccount $account): void
    {
        if (isset($this->recorded[$account->sellerId])) {
            throw SellerPayoutAccountAlreadyLinked::forSellerId($account->sellerId);
        }

        $this->recorded[$account->sellerId] = $account;
    }

    public function findBySellerId(string $sellerId): ?SellerPayoutAccount
    {
        return $this->recorded[$sellerId] ?? null;
    }
}
