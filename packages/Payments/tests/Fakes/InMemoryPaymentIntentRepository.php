<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Tests\Fakes;

use RowBuddy\Payments\Contracts\PaymentIntentRepository;
use RowBuddy\Payments\PaymentIntent;

final class InMemoryPaymentIntentRepository implements PaymentIntentRepository
{
    /** @var array<string, PaymentIntent> */
    public array $recorded = [];

    public function save(PaymentIntent $paymentIntent): void
    {
        $this->recorded[$paymentIntent->id] = $paymentIntent;
    }

    public function findById(string $id): ?PaymentIntent
    {
        return $this->recorded[$id] ?? null;
    }

    public function findByAuctionId(string $auctionId): ?PaymentIntent
    {
        foreach ($this->recorded as $paymentIntent) {
            if ($paymentIntent->auctionId === $auctionId) {
                return $paymentIntent;
            }
        }

        return null;
    }

    public function findByIdForUpdate(string $id): ?PaymentIntent
    {
        return $this->findById($id);
    }

    public function findByStripePaymentIntentId(string $stripePaymentIntentId): ?PaymentIntent
    {
        foreach ($this->recorded as $paymentIntent) {
            if ($paymentIntent->stripePaymentIntentId === $stripePaymentIntentId) {
                return $paymentIntent;
            }
        }

        return null;
    }
}
