<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Contracts;

use RowBuddy\Payments\BuyerPaymentMethod;

/**
 * Domain-facing persistence port. Deliberately expresses no ORM/storage
 * concept. Unlike {@see SellerPayoutAccountRepository}, `save()` is an
 * upsert — a buyer may replace their saved payment method at any time, so
 * this port exposes no "already exists" failure mode.
 */
interface BuyerPaymentMethodRepository
{
    /**
     * Inserts or replaces the one row this buyer owns.
     */
    public function save(BuyerPaymentMethod $method): void;

    public function findByBuyerId(string $buyerId): ?BuyerPaymentMethod;
}
