<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Tests\Fakes;

use RowBuddy\Payments\BuyerPaymentMethod;
use RowBuddy\Payments\Contracts\BuyerPaymentMethodRepository;

final class InMemoryBuyerPaymentMethodRepository implements BuyerPaymentMethodRepository
{
    /** @var array<string, BuyerPaymentMethod> */
    public array $saved = [];

    public function save(BuyerPaymentMethod $method): void
    {
        $this->saved[$method->buyerId] = $method;
    }

    public function findByBuyerId(string $buyerId): ?BuyerPaymentMethod
    {
        return $this->saved[$buyerId] ?? null;
    }
}
