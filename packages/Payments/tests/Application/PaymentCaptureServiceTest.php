<?php

declare(strict_types=1);

use RowBuddy\Payments\Application\PaymentCaptureService;
use RowBuddy\Payments\Events\AuthorizationCancelled;
use RowBuddy\Payments\Events\PaymentCaptured;
use RowBuddy\Payments\Events\PaymentCaptureFailed;
use RowBuddy\Payments\Events\PaymentRefunded;
use RowBuddy\Payments\PaymentIntent;
use RowBuddy\Payments\Tests\Fakes\FakePaymentAuthorizationGateway;
use RowBuddy\Payments\Tests\Fakes\InMemoryPaymentIntentRepository;
use RowBuddy\Payments\Tests\Fakes\RecordingDomainEventPublisher;
use RowBuddy\Payments\Tests\Fakes\RecordingTransactionManager;
use RowBuddy\Payments\ValueObjects\CaptureAttempt;
use RowBuddy\Payments\ValueObjects\PaymentIntentStatus;
use RowBuddy\SharedKernel\Exceptions\NotFoundException;
use RowBuddy\SharedKernel\Support\FrozenClock;

// Fixtures are set up via fromPersistence(), not authorize() — simulating
// an already-existing, already-persisted record (what a real repository
// would return), so no leftover PaymentAuthorized event from
// construction is still sitting in the aggregate when the service under
// test calls releaseEvents().

it('captures an authorized payment intent and publishes PaymentCaptured', function () {
    $paymentIntents = new InMemoryPaymentIntentRepository;
    $paymentIntents->save(PaymentIntent::fromPersistence(
        'payment-1', 'auction-1', 'bid-1', '101', '102', usd(10000), usd(1000), 'pi_stripe_123',
        PaymentIntentStatus::Authorized, new DateTimeImmutable('2026-10-29 10:00:00'),
    ));
    $gateway = new FakePaymentAuthorizationGateway;
    $events = new RecordingDomainEventPublisher;
    $service = new PaymentCaptureService($paymentIntents, $gateway, new RecordingTransactionManager, $events, new FrozenClock);

    $service->capture('auction-1');

    expect($paymentIntents->findById('payment-1')->status())->toBe(PaymentIntentStatus::Captured)
        ->and($gateway->captureCalls)->toBe(['pi_stripe_123'])
        ->and($events->published)->toHaveCount(1)
        ->and($events->published[0])->toBeInstanceOf(PaymentCaptured::class);
});

it('records a failed capture and publishes PaymentCaptureFailed when Stripe declines the capture', function () {
    $paymentIntents = new InMemoryPaymentIntentRepository;
    $paymentIntents->save(PaymentIntent::fromPersistence(
        'payment-1', 'auction-1', 'bid-1', '101', '102', usd(10000), usd(1000), 'pi_stripe_123',
        PaymentIntentStatus::Authorized, new DateTimeImmutable('2026-10-29 10:00:00'),
    ));
    $gateway = new FakePaymentAuthorizationGateway;
    $gateway->nextCaptureAttempt = CaptureAttempt::failed('authorization_expired');
    $events = new RecordingDomainEventPublisher;
    $service = new PaymentCaptureService($paymentIntents, $gateway, new RecordingTransactionManager, $events, new FrozenClock);

    $service->capture('auction-1');

    expect($paymentIntents->findById('payment-1')->status())->toBe(PaymentIntentStatus::CaptureFailed)
        ->and($events->published)->toHaveCount(1)
        ->and($events->published[0])->toBeInstanceOf(PaymentCaptureFailed::class)
        ->and($events->published[0]->payload()['reason'])->toBe('authorization_expired');
});

it('is idempotent: capturing an already-captured payment intent never calls Stripe again', function () {
    $paymentIntents = new InMemoryPaymentIntentRepository;
    $paymentIntent = PaymentIntent::fromPersistence(
        'payment-1', 'auction-1', 'bid-1', '101', '102', usd(10000), usd(1000), 'pi_stripe_123',
        PaymentIntentStatus::Authorized, new DateTimeImmutable('2026-10-29 10:00:00'),
    );
    $paymentIntent->capture(new FrozenClock);
    $paymentIntents->save($paymentIntent);
    $gateway = new FakePaymentAuthorizationGateway;
    $events = new RecordingDomainEventPublisher;
    $service = new PaymentCaptureService($paymentIntents, $gateway, new RecordingTransactionManager, $events, new FrozenClock);

    $service->capture('auction-1');

    expect($gateway->captureCalls)->toBe([])
        ->and($events->published)->toBe([]);
});

it('throws NotFoundException when capturing an auction with no PaymentIntent', function () {
    $service = new PaymentCaptureService(
        new InMemoryPaymentIntentRepository,
        new FakePaymentAuthorizationGateway,
        new RecordingTransactionManager,
        new RecordingDomainEventPublisher,
        new FrozenClock,
    );

    expect(fn () => $service->capture('auction-missing'))->toThrow(NotFoundException::class);
});

it('cancels an authorization and publishes AuthorizationCancelled', function () {
    $paymentIntents = new InMemoryPaymentIntentRepository;
    $paymentIntents->save(PaymentIntent::fromPersistence(
        'payment-1', 'auction-1', 'bid-1', '101', '102', usd(10000), usd(1000), 'pi_stripe_123',
        PaymentIntentStatus::Authorized, new DateTimeImmutable('2026-10-29 10:00:00'),
    ));
    $gateway = new FakePaymentAuthorizationGateway;
    $events = new RecordingDomainEventPublisher;
    $service = new PaymentCaptureService($paymentIntents, $gateway, new RecordingTransactionManager, $events, new FrozenClock);

    $service->cancel('auction-1', 'transfer_window_expired');

    expect($paymentIntents->findById('payment-1')->status())->toBe(PaymentIntentStatus::Cancelled)
        ->and($gateway->cancelCalls)->toBe([['stripePaymentIntentId' => 'pi_stripe_123', 'reason' => 'transfer_window_expired']])
        ->and($events->published)->toHaveCount(1)
        ->and($events->published[0])->toBeInstanceOf(AuthorizationCancelled::class)
        ->and($events->published[0]->payload()['reason'])->toBe('transfer_window_expired');
});

it('is idempotent: cancelling an already-cancelled payment intent never calls Stripe again', function () {
    $paymentIntents = new InMemoryPaymentIntentRepository;
    $paymentIntent = PaymentIntent::fromPersistence(
        'payment-1', 'auction-1', 'bid-1', '101', '102', usd(10000), usd(1000), 'pi_stripe_123',
        PaymentIntentStatus::Authorized, new DateTimeImmutable('2026-10-29 10:00:00'),
    );
    $paymentIntent->cancelAuthorization('already_cancelled', new FrozenClock);
    $paymentIntents->save($paymentIntent);
    $gateway = new FakePaymentAuthorizationGateway;
    $events = new RecordingDomainEventPublisher;
    $service = new PaymentCaptureService($paymentIntents, $gateway, new RecordingTransactionManager, $events, new FrozenClock);

    $service->cancel('auction-1', 'reason');

    expect($gateway->cancelCalls)->toBe([])
        ->and($events->published)->toBe([]);
});

it('throws NotFoundException when cancelling an auction with no PaymentIntent', function () {
    $service = new PaymentCaptureService(
        new InMemoryPaymentIntentRepository,
        new FakePaymentAuthorizationGateway,
        new RecordingTransactionManager,
        new RecordingDomainEventPublisher,
        new FrozenClock,
    );

    expect(fn () => $service->cancel('auction-missing', 'reason'))->toThrow(NotFoundException::class);
});

it('refunds a captured payment intent and publishes PaymentRefunded', function () {
    $paymentIntents = new InMemoryPaymentIntentRepository;
    $paymentIntent = PaymentIntent::fromPersistence(
        'payment-1', 'auction-1', 'bid-1', '101', '102', usd(11000), usd(1000), 'pi_stripe_123',
        PaymentIntentStatus::Authorized, new DateTimeImmutable('2026-10-29 10:00:00'),
    );
    $paymentIntent->capture(new FrozenClock);
    $paymentIntent->releaseEvents();
    $paymentIntents->save($paymentIntent);
    $gateway = new FakePaymentAuthorizationGateway;
    $events = new RecordingDomainEventPublisher;
    $service = new PaymentCaptureService($paymentIntents, $gateway, new RecordingTransactionManager, $events, new FrozenClock);

    $service->refund('auction-1', 'dispute-1', usd(11000), 'buyer is correct');

    expect($paymentIntents->findById('payment-1')->status())->toBe(PaymentIntentStatus::Refunded)
        ->and($paymentIntents->findById('payment-1')->refundedAmount()->equals(usd(11000)))->toBeTrue()
        ->and($gateway->refundCalls)->toHaveCount(1)
        ->and($gateway->refundCalls[0]['stripePaymentIntentId'])->toBe('pi_stripe_123')
        ->and($gateway->refundCalls[0]['amount']->equals(usd(11000)))->toBeTrue()
        ->and($gateway->refundCalls[0]['idempotencyKey'])->toBe('payments.dispute_refund.auction-1.dispute-1')
        ->and($events->published)->toHaveCount(1)
        ->and($events->published[0])->toBeInstanceOf(PaymentRefunded::class);
});

it('is idempotent: refunding a payment intent that is not Captured never calls Stripe', function () {
    $paymentIntents = new InMemoryPaymentIntentRepository;
    $paymentIntents->save(PaymentIntent::fromPersistence(
        'payment-1', 'auction-1', 'bid-1', '101', '102', usd(11000), usd(1000), 'pi_stripe_123',
        PaymentIntentStatus::Authorized, new DateTimeImmutable('2026-10-29 10:00:00'),
    ));
    $gateway = new FakePaymentAuthorizationGateway;
    $events = new RecordingDomainEventPublisher;
    $service = new PaymentCaptureService($paymentIntents, $gateway, new RecordingTransactionManager, $events, new FrozenClock);

    $service->refund('auction-1', 'dispute-1', usd(11000), 'reason');

    expect($gateway->refundCalls)->toBe([])
        ->and($events->published)->toBe([]);
});

it('is idempotent: retrying the same refund after it already succeeded only calls Stripe once', function () {
    $paymentIntents = new InMemoryPaymentIntentRepository;
    $paymentIntent = PaymentIntent::fromPersistence(
        'payment-1', 'auction-1', 'bid-1', '101', '102', usd(11000), usd(1000), 'pi_stripe_123',
        PaymentIntentStatus::Authorized, new DateTimeImmutable('2026-10-29 10:00:00'),
    );
    $paymentIntent->capture(new FrozenClock);
    $paymentIntent->releaseEvents();
    $paymentIntents->save($paymentIntent);
    $gateway = new FakePaymentAuthorizationGateway;
    $events = new RecordingDomainEventPublisher;
    $service = new PaymentCaptureService($paymentIntents, $gateway, new RecordingTransactionManager, $events, new FrozenClock);

    // Simulates a retried DisputeResolved delivery, or a caller retrying
    // after a prior local failure downstream of an already-accepted
    // Stripe call — the same logical operation invoked twice.
    $service->refund('auction-1', 'dispute-1', usd(11000), 'buyer is correct');
    $service->refund('auction-1', 'dispute-1', usd(11000), 'buyer is correct');

    expect($gateway->refundCalls)->toHaveCount(1)
        ->and($events->published)->toHaveCount(1)
        ->and($paymentIntents->findById('payment-1')->status())->toBe(PaymentIntentStatus::Refunded)
        ->and($paymentIntents->findById('payment-1')->refundedAmount()->equals(usd(11000)))->toBeTrue();
});

it('throws NotFoundException when refunding an auction with no PaymentIntent', function () {
    $service = new PaymentCaptureService(
        new InMemoryPaymentIntentRepository,
        new FakePaymentAuthorizationGateway,
        new RecordingTransactionManager,
        new RecordingDomainEventPublisher,
        new FrozenClock,
    );

    expect(fn () => $service->refund('auction-missing', 'dispute-1', usd(1000), 'reason'))->toThrow(NotFoundException::class);
});
