<?php

declare(strict_types=1);

use RowBuddy\Payments\Events\AuthorizationCancelled;
use RowBuddy\Payments\Events\PaymentAuthorizationFailed;
use RowBuddy\Payments\Events\PaymentAuthorized;
use RowBuddy\Payments\Events\PaymentCaptured;
use RowBuddy\Payments\Events\PaymentCaptureFailed;
use RowBuddy\Payments\Events\PaymentRefunded;
use RowBuddy\Payments\Exceptions\IllegalStateTransition;
use RowBuddy\Payments\Exceptions\InvalidRefundAmount;
use RowBuddy\Payments\Exceptions\TransactionValueLimitExceeded;
use RowBuddy\Payments\Exceptions\UnsupportedCurrency;
use RowBuddy\Payments\PaymentIntent;
use RowBuddy\Payments\ValueObjects\PaymentIntentStatus;
use RowBuddy\SharedKernel\Support\FrozenClock;

it('authorizes a payment and raises a PaymentAuthorized event', function () {
    $decidedAt = new DateTimeImmutable('2026-10-10 10:00:00');

    $paymentIntent = PaymentIntent::authorize(
        'payment-1',
        'auction-1',
        'bid-1',
        'seller-1',
        'buyer-1',
        usd(10000),
        usd(1000),
        usd(50000),
        'pi_stripe_123',
        new FrozenClock($decidedAt),
    );

    expect($paymentIntent->id)->toBe('payment-1')
        ->and($paymentIntent->auctionId)->toBe('auction-1')
        ->and($paymentIntent->winningBidId)->toBe('bid-1')
        ->and($paymentIntent->sellerId)->toBe('seller-1')
        ->and($paymentIntent->buyerId)->toBe('buyer-1')
        ->and($paymentIntent->amount->equals(usd(10000)))->toBeTrue()
        ->and($paymentIntent->feeAmount->equals(usd(1000)))->toBeTrue()
        ->and($paymentIntent->stripePaymentIntentId)->toBe('pi_stripe_123')
        ->and($paymentIntent->status())->toBe(PaymentIntentStatus::Authorized)
        ->and($paymentIntent->decidedAt)->toEqual($decidedAt);

    $events = $paymentIntent->releaseEvents();
    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(PaymentAuthorized::class)
        ->and($events[0]->payload())->toBe([
            'payment_intent_id' => 'payment-1',
            'auction_id' => 'auction-1',
            'winning_bid_id' => 'bid-1',
            'amount_minor_units' => 10000,
            'amount_currency' => 'USD',
            'fee_amount_minor_units' => 1000,
        ]);
});

it('rejects authorization when the amount currency does not match the transaction value limit', function () {
    expect(fn () => PaymentIntent::authorize(
        'payment-1',
        'auction-1',
        'bid-1',
        'seller-1',
        'buyer-1',
        eur(10000),
        eur(1000),
        usd(50000),
        'pi_stripe_123',
        new FrozenClock,
    ))->toThrow(UnsupportedCurrency::class);
});

it('rejects authorization when the amount exceeds the transaction value limit', function () {
    expect(fn () => PaymentIntent::authorize(
        'payment-1',
        'auction-1',
        'bid-1',
        'seller-1',
        'buyer-1',
        usd(50001),
        usd(5000),
        usd(50000),
        'pi_stripe_123',
        new FrozenClock,
    ))->toThrow(TransactionValueLimitExceeded::class);
});

it('allows authorization when the amount exactly equals the transaction value limit', function () {
    $paymentIntent = PaymentIntent::authorize(
        'payment-1',
        'auction-1',
        'bid-1',
        'seller-1',
        'buyer-1',
        usd(50000),
        usd(5000),
        usd(50000),
        'pi_stripe_123',
        new FrozenClock,
    );

    expect($paymentIntent->status())->toBe(PaymentIntentStatus::Authorized);
});

it('records a declined authorization and raises a PaymentAuthorizationFailed event', function () {
    $decidedAt = new DateTimeImmutable('2026-10-10 10:00:00');

    $paymentIntent = PaymentIntent::declineAuthorization(
        'payment-2',
        'auction-1',
        'bid-1',
        'seller-1',
        'buyer-1',
        usd(10000),
        usd(1000),
        usd(50000),
        'card_declined',
        new FrozenClock($decidedAt),
    );

    expect($paymentIntent->status())->toBe(PaymentIntentStatus::Failed)
        ->and($paymentIntent->stripePaymentIntentId)->toBeNull()
        ->and($paymentIntent->decidedAt)->toEqual($decidedAt);

    $events = $paymentIntent->releaseEvents();
    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(PaymentAuthorizationFailed::class)
        ->and($events[0]->payload())->toBe([
            'payment_intent_id' => 'payment-2',
            'auction_id' => 'auction-1',
            'winning_bid_id' => 'bid-1',
            'amount_minor_units' => 10000,
            'amount_currency' => 'USD',
            'reason' => 'card_declined',
        ]);
});

it('rejects a declined-authorization record when the amount currency does not match the limit', function () {
    expect(fn () => PaymentIntent::declineAuthorization(
        'payment-2',
        'auction-1',
        'bid-1',
        'seller-1',
        'buyer-1',
        eur(10000),
        eur(1000),
        usd(50000),
        'card_declined',
        new FrozenClock,
    ))->toThrow(UnsupportedCurrency::class);
});

it('rejects a declined-authorization record when the amount exceeds the limit', function () {
    expect(fn () => PaymentIntent::declineAuthorization(
        'payment-2',
        'auction-1',
        'bid-1',
        'seller-1',
        'buyer-1',
        usd(50001),
        usd(5000),
        usd(50000),
        'card_declined',
        new FrozenClock,
    ))->toThrow(TransactionValueLimitExceeded::class);
});

it('captures an authorized payment and raises a PaymentCaptured event', function () {
    $paymentIntent = PaymentIntent::authorize(
        'payment-1', 'auction-1', 'bid-1', 'seller-1', 'buyer-1', usd(10000), usd(1000), usd(50000), 'pi_stripe_123', new FrozenClock,
    );
    $paymentIntent->releaseEvents();

    $paymentIntent->capture(new FrozenClock);

    expect($paymentIntent->status())->toBe(PaymentIntentStatus::Captured);

    $events = $paymentIntent->releaseEvents();
    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(PaymentCaptured::class)
        ->and($events[0]->payload())->toBe([
            'payment_intent_id' => 'payment-1',
            'auction_id' => 'auction-1',
        ]);
});

it('rejects capturing a payment intent that is not Authorized', function () {
    $paymentIntent = PaymentIntent::declineAuthorization(
        'payment-2', 'auction-1', 'bid-1', 'seller-1', 'buyer-1', usd(10000), usd(1000), usd(50000), 'card_declined', new FrozenClock,
    );

    expect(fn () => $paymentIntent->capture(new FrozenClock))->toThrow(IllegalStateTransition::class);
});

it('records a failed capture and raises a PaymentCaptureFailed event', function () {
    $paymentIntent = PaymentIntent::authorize(
        'payment-1', 'auction-1', 'bid-1', 'seller-1', 'buyer-1', usd(10000), usd(1000), usd(50000), 'pi_stripe_123', new FrozenClock,
    );
    $paymentIntent->releaseEvents();

    $paymentIntent->failCapture('authorization_expired', new FrozenClock);

    expect($paymentIntent->status())->toBe(PaymentIntentStatus::CaptureFailed);

    $events = $paymentIntent->releaseEvents();
    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(PaymentCaptureFailed::class)
        ->and($events[0]->payload())->toBe([
            'payment_intent_id' => 'payment-1',
            'auction_id' => 'auction-1',
            'reason' => 'authorization_expired',
        ]);
});

it('rejects failing capture on a payment intent that is not Authorized', function () {
    $paymentIntent = PaymentIntent::authorize(
        'payment-1', 'auction-1', 'bid-1', 'seller-1', 'buyer-1', usd(10000), usd(1000), usd(50000), 'pi_stripe_123', new FrozenClock,
    );
    $paymentIntent->capture(new FrozenClock);

    expect(fn () => $paymentIntent->failCapture('reason', new FrozenClock))->toThrow(IllegalStateTransition::class);
});

it('cancels an authorization without a capture attempt and raises an AuthorizationCancelled event', function () {
    $paymentIntent = PaymentIntent::authorize(
        'payment-1', 'auction-1', 'bid-1', 'seller-1', 'buyer-1', usd(10000), usd(1000), usd(50000), 'pi_stripe_123', new FrozenClock,
    );
    $paymentIntent->releaseEvents();

    $paymentIntent->cancelAuthorization('transfer_window_expired', new FrozenClock);

    expect($paymentIntent->status())->toBe(PaymentIntentStatus::Cancelled);

    $events = $paymentIntent->releaseEvents();
    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(AuthorizationCancelled::class)
        ->and($events[0]->payload())->toBe([
            'payment_intent_id' => 'payment-1',
            'auction_id' => 'auction-1',
            'reason' => 'transfer_window_expired',
        ]);
});

it('rejects cancelling a payment intent that is not Authorized', function () {
    $paymentIntent = PaymentIntent::authorize(
        'payment-1', 'auction-1', 'bid-1', 'seller-1', 'buyer-1', usd(10000), usd(1000), usd(50000), 'pi_stripe_123', new FrozenClock,
    );
    $paymentIntent->cancelAuthorization('reason', new FrozenClock);

    expect(fn () => $paymentIntent->cancelAuthorization('reason', new FrozenClock))->toThrow(IllegalStateTransition::class);
});

it('refunds the full captured amount and raises a PaymentRefunded event', function () {
    $paymentIntent = PaymentIntent::authorize(
        'payment-1', 'auction-1', 'bid-1', 'seller-1', 'buyer-1', usd(11000), usd(1000), usd(50000), 'pi_stripe_123', new FrozenClock,
    );
    $paymentIntent->capture(new FrozenClock);
    $paymentIntent->releaseEvents();

    $paymentIntent->refund(usd(11000), 'buyer is correct', new FrozenClock);

    expect($paymentIntent->status())->toBe(PaymentIntentStatus::Refunded)
        ->and($paymentIntent->refundedAmount()->equals(usd(11000)))->toBeTrue()
        ->and($paymentIntent->remainingCapturedAmount()->isZero())->toBeTrue();

    $events = $paymentIntent->releaseEvents();
    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(PaymentRefunded::class)
        ->and($events[0]->payload())->toBe([
            'payment_intent_id' => 'payment-1',
            'auction_id' => 'auction-1',
            'amount_minor_units' => 11000,
            'amount_currency' => 'USD',
            'reason' => 'buyer is correct',
        ]);
});

it('refunds a partial amount smaller than the full captured total, retaining the unreimbursed remainder', function () {
    $paymentIntent = PaymentIntent::authorize(
        'payment-1', 'auction-1', 'bid-1', 'seller-1', 'buyer-1', usd(11000), usd(1000), usd(50000), 'pi_stripe_123', new FrozenClock,
    );
    $paymentIntent->capture(new FrozenClock);

    $paymentIntent->refund(usd(5000), 'partial fault on both sides', new FrozenClock);

    expect($paymentIntent->status())->toBe(PaymentIntentStatus::Refunded)
        ->and($paymentIntent->refundedAmount()->equals(usd(5000)))->toBeTrue()
        ->and($paymentIntent->remainingCapturedAmount()->equals(usd(6000)))->toBeTrue();
});

it('reports the full captured amount as remaining when nothing has been refunded yet', function () {
    $paymentIntent = PaymentIntent::authorize(
        'payment-1', 'auction-1', 'bid-1', 'seller-1', 'buyer-1', usd(11000), usd(1000), usd(50000), 'pi_stripe_123', new FrozenClock,
    );
    $paymentIntent->capture(new FrozenClock);

    expect($paymentIntent->refundedAmount())->toBeNull()
        ->and($paymentIntent->remainingCapturedAmount()->equals(usd(11000)))->toBeTrue();
});

it('assertRefundable validates without mutating, so a caller can check before ever calling Stripe', function () {
    $paymentIntent = PaymentIntent::authorize(
        'payment-1', 'auction-1', 'bid-1', 'seller-1', 'buyer-1', usd(11000), usd(1000), usd(50000), 'pi_stripe_123', new FrozenClock,
    );
    $paymentIntent->capture(new FrozenClock);

    $paymentIntent->assertRefundable(usd(11000));

    expect($paymentIntent->status())->toBe(PaymentIntentStatus::Captured)
        ->and($paymentIntent->refundedAmount())->toBeNull();
});

it('assertRefundable throws for the same invalid cases refund() itself rejects', function () {
    $paymentIntent = PaymentIntent::authorize(
        'payment-1', 'auction-1', 'bid-1', 'seller-1', 'buyer-1', usd(11000), usd(1000), usd(50000), 'pi_stripe_123', new FrozenClock,
    );
    $paymentIntent->capture(new FrozenClock);

    expect(fn () => $paymentIntent->assertRefundable(usd(11001)))->toThrow(InvalidRefundAmount::class);
});

it('rejects refunding a payment intent that is not Captured', function () {
    $paymentIntent = PaymentIntent::authorize(
        'payment-1', 'auction-1', 'bid-1', 'seller-1', 'buyer-1', usd(11000), usd(1000), usd(50000), 'pi_stripe_123', new FrozenClock,
    );

    expect(fn () => $paymentIntent->refund(usd(11000), 'reason', new FrozenClock))->toThrow(IllegalStateTransition::class);
});

it('rejects a zero refund amount', function () {
    $paymentIntent = PaymentIntent::authorize(
        'payment-1', 'auction-1', 'bid-1', 'seller-1', 'buyer-1', usd(11000), usd(1000), usd(50000), 'pi_stripe_123', new FrozenClock,
    );
    $paymentIntent->capture(new FrozenClock);

    expect(fn () => $paymentIntent->refund(usd(0), 'reason', new FrozenClock))->toThrow(InvalidRefundAmount::class);
});

it('rejects a refund amount in a different currency than the captured total', function () {
    $paymentIntent = PaymentIntent::authorize(
        'payment-1', 'auction-1', 'bid-1', 'seller-1', 'buyer-1', usd(11000), usd(1000), usd(50000), 'pi_stripe_123', new FrozenClock,
    );
    $paymentIntent->capture(new FrozenClock);

    expect(fn () => $paymentIntent->refund(eur(11000), 'reason', new FrozenClock))->toThrow(InvalidRefundAmount::class);
});

it('rejects a refund amount exceeding the captured total', function () {
    $paymentIntent = PaymentIntent::authorize(
        'payment-1', 'auction-1', 'bid-1', 'seller-1', 'buyer-1', usd(11000), usd(1000), usd(50000), 'pi_stripe_123', new FrozenClock,
    );
    $paymentIntent->capture(new FrozenClock);

    expect(fn () => $paymentIntent->refund(usd(11001), 'reason', new FrozenClock))->toThrow(InvalidRefundAmount::class);
});

it('rejects refunding a payment intent that has already been refunded', function () {
    $paymentIntent = PaymentIntent::authorize(
        'payment-1', 'auction-1', 'bid-1', 'seller-1', 'buyer-1', usd(11000), usd(1000), usd(50000), 'pi_stripe_123', new FrozenClock,
    );
    $paymentIntent->capture(new FrozenClock);
    $paymentIntent->refund(usd(11000), 'first refund', new FrozenClock);

    expect(fn () => $paymentIntent->refund(usd(1000), 'second refund', new FrozenClock))->toThrow(IllegalStateTransition::class);
});

it('releasing events clears them so they are not dispatched twice', function () {
    $paymentIntent = PaymentIntent::authorize(
        'payment-1',
        'auction-1',
        'bid-1',
        'seller-1',
        'buyer-1',
        usd(10000),
        usd(1000),
        usd(50000),
        'pi_stripe_123',
        new FrozenClock,
    );

    $paymentIntent->releaseEvents();

    expect($paymentIntent->releaseEvents())->toBe([]);
});

it('reconstitutes from persistence without raising any events', function () {
    $decidedAt = new DateTimeImmutable('2026-10-10 10:00:00');

    $paymentIntent = PaymentIntent::fromPersistence(
        'payment-1',
        'auction-1',
        'bid-1',
        'seller-1',
        'buyer-1',
        usd(10000),
        usd(1000),
        'pi_stripe_123',
        PaymentIntentStatus::Authorized,
        $decidedAt,
    );

    expect($paymentIntent->amount->equals(usd(10000)))->toBeTrue()
        ->and($paymentIntent->stripePaymentIntentId)->toBe('pi_stripe_123')
        ->and($paymentIntent->status())->toBe(PaymentIntentStatus::Authorized)
        ->and($paymentIntent->decidedAt)->toEqual($decidedAt)
        ->and($paymentIntent->releaseEvents())->toBe([]);
});
