<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Tests\Fakes;

use RowBuddy\Payments\Contracts\PaymentAuthorizationGateway;
use RowBuddy\Payments\ValueObjects\AuthorizationAttempt;
use RowBuddy\Payments\ValueObjects\CaptureAttempt;
use RowBuddy\SharedKernel\ValueObjects\Money;

final class FakePaymentAuthorizationGateway implements PaymentAuthorizationGateway
{
    /** @var list<array{idempotencyKey: string, amount: Money, stripePaymentMethodId: string, description: string}> */
    public array $calls = [];

    /** @var list<string> */
    public array $captureCalls = [];

    /** @var list<array{stripePaymentIntentId: string, reason: string}> */
    public array $cancelCalls = [];

    /** @var list<array{stripePaymentIntentId: string, amount: Money, idempotencyKey: string}> */
    public array $refundCalls = [];

    public AuthorizationAttempt $nextAttempt;

    public CaptureAttempt $nextCaptureAttempt;

    public function __construct()
    {
        $this->nextAttempt = AuthorizationAttempt::succeeded('pi_fake');
        $this->nextCaptureAttempt = CaptureAttempt::succeeded();
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

    public function capture(string $stripePaymentIntentId): CaptureAttempt
    {
        $this->captureCalls[] = $stripePaymentIntentId;

        return $this->nextCaptureAttempt;
    }

    public function cancel(string $stripePaymentIntentId, string $reason): void
    {
        $this->cancelCalls[] = [
            'stripePaymentIntentId' => $stripePaymentIntentId,
            'reason' => $reason,
        ];
    }

    public function refund(string $stripePaymentIntentId, Money $amount, string $idempotencyKey): void
    {
        $this->refundCalls[] = [
            'stripePaymentIntentId' => $stripePaymentIntentId,
            'amount' => $amount,
            'idempotencyKey' => $idempotencyKey,
        ];
    }
}
