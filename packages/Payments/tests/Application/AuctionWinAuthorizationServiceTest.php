<?php

declare(strict_types=1);

use RowBuddy\Payments\Application\AuctionWinAuthorizationService;
use RowBuddy\Payments\Application\FeeCalculator;
use RowBuddy\Payments\Application\FixedPlatformFeePolicy;
use RowBuddy\Payments\Application\FixedTransactionValueLimitPolicy;
use RowBuddy\Payments\Events\PaymentAuthorizationFailed;
use RowBuddy\Payments\Events\PaymentAuthorized;
use RowBuddy\Payments\Exceptions\TransactionValueLimitExceeded;
use RowBuddy\Payments\Exceptions\UnsupportedCurrency;
use RowBuddy\Payments\Tests\Fakes\FakePaymentAuthorizationGateway;
use RowBuddy\Payments\Tests\Fakes\InMemoryPaymentIntentRepository;
use RowBuddy\Payments\Tests\Fakes\RecordingDomainEventPublisher;
use RowBuddy\Payments\ValueObjects\AuthorizationAttempt;
use RowBuddy\Payments\ValueObjects\PaymentIntentStatus;
use RowBuddy\SharedKernel\Support\FrozenClock;

it('authorizes the buyer total (bid plus fee) and persists an Authorized PaymentIntent', function () {
    $paymentIntents = new InMemoryPaymentIntentRepository;
    $gateway = new FakePaymentAuthorizationGateway;
    $gateway->nextAttempt = AuthorizationAttempt::succeeded('pi_stripe_123');
    $events = new RecordingDomainEventPublisher;
    $service = new AuctionWinAuthorizationService(
        $paymentIntents,
        $gateway,
        new FeeCalculator(new FixedPlatformFeePolicy(10)),
        new FixedTransactionValueLimitPolicy(usd(5000000)),
        $events,
        new FrozenClock,
    );

    $paymentIntent = $service->handle(
        'payment-1',
        'auction-1',
        'bid-1',
        '101',
        '102',
        usd(10000),
        'pm_card_visa',
    );

    expect($paymentIntent->status())->toBe(PaymentIntentStatus::Authorized)
        ->and($paymentIntent->amount->equals(usd(11000)))->toBeTrue()
        ->and($paymentIntent->feeAmount->equals(usd(1000)))->toBeTrue()
        ->and($paymentIntents->findById('payment-1'))->not->toBeNull();

    expect($gateway->calls)->toHaveCount(1)
        ->and($gateway->calls[0]['idempotencyKey'])->toBe('payments.auction_win_authorization.auction-1')
        ->and($gateway->calls[0]['amount']->equals(usd(11000)))->toBeTrue()
        ->and($gateway->calls[0]['stripePaymentMethodId'])->toBe('pm_card_visa');

    expect($events->published)->toHaveCount(1)
        ->and($events->published[0])->toBeInstanceOf(PaymentAuthorized::class);
});

it('records a Failed PaymentIntent and publishes PaymentAuthorizationFailed when Stripe declines', function () {
    $paymentIntents = new InMemoryPaymentIntentRepository;
    $gateway = new FakePaymentAuthorizationGateway;
    $gateway->nextAttempt = AuthorizationAttempt::failed('card_declined');
    $events = new RecordingDomainEventPublisher;
    $service = new AuctionWinAuthorizationService(
        $paymentIntents,
        $gateway,
        new FeeCalculator(new FixedPlatformFeePolicy(10)),
        new FixedTransactionValueLimitPolicy(usd(5000000)),
        $events,
        new FrozenClock,
    );

    $paymentIntent = $service->handle('payment-1', 'auction-1', 'bid-1', '101', '102', usd(10000), 'pm_card_declined');

    expect($paymentIntent->status())->toBe(PaymentIntentStatus::Failed);

    expect($events->published)->toHaveCount(1)
        ->and($events->published[0])->toBeInstanceOf(PaymentAuthorizationFailed::class)
        ->and($events->published[0]->payload()['reason'])->toBe('card_declined');
});

it('is idempotent: a second call for the same auction never calls Stripe again', function () {
    $paymentIntents = new InMemoryPaymentIntentRepository;
    $gateway = new FakePaymentAuthorizationGateway;
    $events = new RecordingDomainEventPublisher;
    $service = new AuctionWinAuthorizationService(
        $paymentIntents,
        $gateway,
        new FeeCalculator(new FixedPlatformFeePolicy(10)),
        new FixedTransactionValueLimitPolicy(usd(5000000)),
        $events,
        new FrozenClock,
    );

    $first = $service->handle('payment-1', 'auction-1', 'bid-1', '101', '102', usd(10000), 'pm_card_visa');
    $second = $service->handle('payment-2', 'auction-1', 'bid-1', '101', '102', usd(10000), 'pm_card_visa');

    expect($second->id)->toBe($first->id)
        ->and($gateway->calls)->toHaveCount(1)
        ->and($events->published)->toHaveCount(1);
});

it('rejects and never calls Stripe when the total exceeds the transaction value limit', function () {
    $paymentIntents = new InMemoryPaymentIntentRepository;
    $gateway = new FakePaymentAuthorizationGateway;
    $events = new RecordingDomainEventPublisher;
    $service = new AuctionWinAuthorizationService(
        $paymentIntents,
        $gateway,
        new FeeCalculator(new FixedPlatformFeePolicy(10)),
        new FixedTransactionValueLimitPolicy(usd(50000)),
        $events,
        new FrozenClock,
    );

    expect(fn () => $service->handle('payment-1', 'auction-1', 'bid-1', '101', '102', usd(50000), 'pm_card_visa'))
        ->toThrow(TransactionValueLimitExceeded::class);

    expect($gateway->calls)->toBe([])
        ->and($paymentIntents->recorded)->toBe([])
        ->and($events->published)->toBe([]);
});

it('rejects and never calls Stripe when the winning amount currency is not supported', function () {
    $paymentIntents = new InMemoryPaymentIntentRepository;
    $gateway = new FakePaymentAuthorizationGateway;
    $events = new RecordingDomainEventPublisher;
    $service = new AuctionWinAuthorizationService(
        $paymentIntents,
        $gateway,
        new FeeCalculator(new FixedPlatformFeePolicy(10)),
        new FixedTransactionValueLimitPolicy(usd(5000000)),
        $events,
        new FrozenClock,
    );

    expect(fn () => $service->handle('payment-1', 'auction-1', 'bid-1', '101', '102', eur(10000), 'pm_card_visa'))
        ->toThrow(UnsupportedCurrency::class);

    expect($gateway->calls)->toBe([])
        ->and($paymentIntents->recorded)->toBe([]);
});
