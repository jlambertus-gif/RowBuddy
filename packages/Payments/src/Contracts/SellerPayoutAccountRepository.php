<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Contracts;

use RowBuddy\Payments\Exceptions\SellerPayoutAccountAlreadyLinked;
use RowBuddy\Payments\SellerPayoutAccount;

/**
 * Domain-facing persistence port. Deliberately expresses no ORM/storage
 * concept, and deliberately exposes no update path — a seller's payout
 * account, once linked, is never re-linked or re-pointed to a different
 * Stripe account in the MVP.
 */
interface SellerPayoutAccountRepository
{
    /**
     * @throws SellerPayoutAccountAlreadyLinked if this seller already has
     *                                          a linked payout account
     */
    public function record(SellerPayoutAccount $account): void;

    public function findBySellerId(string $sellerId): ?SellerPayoutAccount;
}
