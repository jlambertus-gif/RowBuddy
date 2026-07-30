<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RowBuddy\Disputes\Application\DisputeRefundTriggerService;
use RowBuddy\Disputes\ValueObjects\DisputeResolutionOutcome;
use RowBuddy\Payments\Contracts\PaymentAuthorizationGateway;
use RowBuddy\Payments\Contracts\PaymentIntentRepository;
use RowBuddy\Payments\PaymentIntent;
use RowBuddy\Payments\ValueObjects\AuthorizationAttempt;
use RowBuddy\Payments\ValueObjects\CaptureAttempt;
use RowBuddy\Payments\ValueObjects\PaymentIntentStatus;
use RowBuddy\SharedKernel\Support\FrozenClock;
use RowBuddy\SharedKernel\ValueObjects\Currency;
use RowBuddy\SharedKernel\ValueObjects\Money;

uses(RefreshDatabase::class);

/**
 * A local, in-memory stand-in for the real Stripe-backed gateway,
 * recording every call so this test can assert on Stripe-call counts
 * without a real Stripe API. Lives in this test file rather than
 * packages/Payments/tests/Fakes/FakePaymentAuthorizationGateway.php
 * because apps/web cannot reach a package's autoload-dev-only test
 * namespace — only its production src/ autoload.
 */
final class LocalFakePaymentAuthorizationGateway implements PaymentAuthorizationGateway
{
    /** @var list<array{stripePaymentIntentId: string, amount: Money, idempotencyKey: string}> */
    public array $refundCalls = [];

    public function authorize(string $idempotencyKey, Money $amount, string $stripePaymentMethodId, string $description): AuthorizationAttempt
    {
        return AuthorizationAttempt::succeeded('pi_fake');
    }

    public function capture(string $stripePaymentIntentId): CaptureAttempt
    {
        return CaptureAttempt::succeeded();
    }

    public function cancel(string $stripePaymentIntentId, string $reason): void {}

    public function refund(string $stripePaymentIntentId, Money $amount, string $idempotencyKey): void
    {
        $this->refundCalls[] = [
            'stripePaymentIntentId' => $stripePaymentIntentId,
            'amount' => $amount,
            'idempotencyKey' => $idempotencyKey,
        ];
    }
}

it('invoking DisputeRefundTriggerService twice for the same dispute only refunds once end-to-end', function () {
    $fakeGateway = new LocalFakePaymentAuthorizationGateway;
    app()->bind(PaymentAuthorizationGateway::class, fn () => $fakeGateway);

    $paymentIntents = app(PaymentIntentRepository::class);
    $auctionId = (string) Str::uuid();
    $paymentIntent = PaymentIntent::authorize(
        (string) Str::uuid(),
        $auctionId,
        (string) Str::uuid(),
        '1',
        '2',
        new Money(11000, new Currency('USD')),
        new Money(1000, new Currency('USD')),
        new Money(100000, new Currency('USD')),
        'pi_wiring_test',
        new FrozenClock,
    );
    $paymentIntent->capture(new FrozenClock);
    $paymentIntent->releaseEvents();
    $paymentIntents->save($paymentIntent);

    $trigger = app(DisputeRefundTriggerService::class);
    $amount = new Money(11000, new Currency('USD'));

    // Simulates the same DisputeResolved delivered twice (a retried
    // listener, or a genuine retry after a prior local failure
    // downstream of an already-accepted Stripe call).
    $trigger->handle('dispute-1', $auctionId, DisputeResolutionOutcome::RefundToBuyer, $amount, 'buyer is correct');
    $trigger->handle('dispute-1', $auctionId, DisputeResolutionOutcome::RefundToBuyer, $amount, 'buyer is correct');

    expect($fakeGateway->refundCalls)->toHaveCount(1)
        ->and($fakeGateway->refundCalls[0]['idempotencyKey'])->toBe("payments.dispute_refund.{$auctionId}.dispute-1");

    $found = $paymentIntents->findByAuctionId($auctionId);
    expect($found->status())->toBe(PaymentIntentStatus::Refunded)
        ->and($found->refundedAmount()->equals($amount))->toBeTrue();
});
