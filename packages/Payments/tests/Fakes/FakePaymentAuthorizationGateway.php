<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Tests\Fakes;

use RowBuddy\Payments\Contracts\PaymentAuthorizationGateway;
use RowBuddy\Payments\ValueObjects\AuthorizationAttempt;
use RowBuddy\SharedKernel\ValueObjects\Money;

final class FakePaymentAuthorizationGateway implements PaymentAuthorizationGateway
{
    /** @var list<array{idempotencyKey: string, amount: Money, stripePaymentMethodId: string, description: string}> */
    public array $calls = [];

    public AuthorizationAttempt $nextAttempt;

    public function __construct()
    {
        $this->nextAttempt = AuthorizationAttempt::succeeded('pi_fake');
    }

    public function authorize(
        string $idempotencyKey,
        Money $amount,
        string $stripePaymentMethodId,
        string $description,
    ): AuthorizationAttempt {
        $this->calls[] = [
            'idempotencyKey' => $idempotencyKey,
            'amount' => $amount,
            'stripePaymentMethodId' => $stripePaymentMethodId,
            'description' => $description,
        ];

        return $this->nextAttempt;
    }
}
