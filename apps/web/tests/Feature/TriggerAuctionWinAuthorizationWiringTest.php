<?php

declare(strict_types=1);

use App\Exceptions\MissingBuyerPaymentMethod;
use App\Listeners\TriggerAuctionWinAuthorization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RowBuddy\Auctions\Events\AuctionWon;
use RowBuddy\Auctions\Infrastructure\Eloquent\AuctionModel;
use RowBuddy\Bids\Infrastructure\Eloquent\BidModel;
use RowBuddy\Payments\BuyerPaymentMethod;
use RowBuddy\Payments\Contracts\BuyerPaymentMethodRepository;
use RowBuddy\Payments\Contracts\PaymentAuthorizationGateway;
use RowBuddy\Payments\Infrastructure\Eloquent\PaymentIntentModel;
use RowBuddy\Payments\ValueObjects\AuthorizationAttempt;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\ValueObjects\Currency;
use RowBuddy\SharedKernel\ValueObjects\Money;
use RowBuddy\Transfers\Infrastructure\Eloquent\TransferModel;

uses(RefreshDatabase::class);

/**
 * Real User rows are required, not bare integer ids: Notifications'
 * SendTransferIssuedNotification/SendPaymentAuthorizationFailedNotification
 * listeners also react to these same domain events and need a real email
 * on file for the recipient.
 *
 * @return array{0: string, 1: string, 2: int, 3: int}
 */
function createWonAuctionFixture(): array
{
    $auctionId = (string) Str::uuid();
    $winningBidId = (string) Str::uuid();
    $sellerId = User::factory()->create()->id;
    $buyerId = User::factory()->create()->id;

    AuctionModel::query()->create([
        'id' => $auctionId,
        'queue_id' => (string) Str::uuid(),
        'seller_id' => $sellerId,
        'presence_session_id' => (string) Str::uuid(),
        'starting_price_minor_units' => 1000,
        'starting_price_currency' => 'USD',
        'opened_at' => now(),
        'closes_at' => now()->addMinutes(30),
        'status' => 'won',
        'winning_bid_id' => $winningBidId,
        'winning_amount_minor_units' => 1500,
        'winning_amount_currency' => 'USD',
    ]);

    BidModel::query()->create([
        'id' => $winningBidId,
        'auction_id' => $auctionId,
        'bidder_id' => $buyerId,
        'amount_minor_units' => 1500,
        'amount_currency' => 'USD',
        'placed_at' => now(),
    ]);

    return [$auctionId, $winningBidId, $sellerId, $buyerId];
}

it('authorizes exactly once for a winner with a valid saved payment method', function () {
    [$auctionId, $winningBidId, $sellerId, $buyerId] = createWonAuctionFixture();
    app(BuyerPaymentMethodRepository::class)->save(
        BuyerPaymentMethod::save((string) $buyerId, 'cus_fake', 'pm_fake', app(ClockInterface::class)),
    );

    $this->app->bind(PaymentAuthorizationGateway::class, fn () => new class implements PaymentAuthorizationGateway
    {
        public int $authorizeCalls = 0;

        public function authorize(string $idempotencyKey, Money $amount, string $stripePaymentMethodId, string $description): AuthorizationAttempt
        {
            $this->authorizeCalls++;

            return AuthorizationAttempt::succeeded('pi_fake_123');
        }

        public function capture(string $stripePaymentIntentId): never
        {
            throw new RuntimeException('Unreachable in this test.');
        }

        public function cancel(string $stripePaymentIntentId, string $reason): void {}

        public function refund(string $stripePaymentIntentId, Money $amount, string $idempotencyKey): void {}
    });

    $event = new AuctionWon(app(ClockInterface::class), $auctionId, $winningBidId, new Money(1500, new Currency('USD')));
    app(TriggerAuctionWinAuthorization::class)->handle($event);

    $paymentIntent = PaymentIntentModel::query()->where('auction_id', $auctionId)->first();
    expect($paymentIntent)->not->toBeNull()
        ->and($paymentIntent->status)->toBe('authorized')
        ->and((string) $paymentIntent->seller_id)->toBe((string) $sellerId)
        ->and((string) $paymentIntent->buyer_id)->toBe((string) $buyerId);
});

it('does not create a PaymentIntent when the winning buyer has no saved payment method, failing through an explicit exception', function () {
    [$auctionId, $winningBidId] = createWonAuctionFixture();

    // Rebinding is required purely so that resolving the listener's
    // dependency graph (AuctionWinAuthorizationService -> the real Stripe
    // gateway -> a real StripeClient) does not itself throw for lack of a
    // configured STRIPE_SECRET in this test environment. The stub below
    // is never actually invoked: the missing-payment-method check fails
    // before AuctionWinAuthorizationService::handle() is ever called.
    $this->app->bind(PaymentAuthorizationGateway::class, fn () => new class implements PaymentAuthorizationGateway
    {
        public function authorize(string $idempotencyKey, Money $amount, string $stripePaymentMethodId, string $description): never
        {
            throw new RuntimeException('Unreachable in this test.');
        }

        public function capture(string $stripePaymentIntentId): never
        {
            throw new RuntimeException('Unreachable in this test.');
        }

        public function cancel(string $stripePaymentIntentId, string $reason): void {}

        public function refund(string $stripePaymentIntentId, Money $amount, string $idempotencyKey): void {}
    });

    $event = new AuctionWon(app(ClockInterface::class), $auctionId, $winningBidId, new Money(1500, new Currency('USD')));

    expect(fn () => app(TriggerAuctionWinAuthorization::class)->handle($event))
        ->toThrow(MissingBuyerPaymentMethod::class);

    expect(PaymentIntentModel::query()->where('auction_id', $auctionId)->exists())->toBeFalse();
});

it('never creates a Transfer when the authorization attempt is declined', function () {
    [$auctionId, $winningBidId, , $buyerId] = createWonAuctionFixture();
    app(BuyerPaymentMethodRepository::class)->save(
        BuyerPaymentMethod::save((string) $buyerId, 'cus_fake', 'pm_fake', app(ClockInterface::class)),
    );

    $this->app->bind(PaymentAuthorizationGateway::class, fn () => new class implements PaymentAuthorizationGateway
    {
        public function authorize(string $idempotencyKey, Money $amount, string $stripePaymentMethodId, string $description): AuthorizationAttempt
        {
            return AuthorizationAttempt::failed('card_declined');
        }

        public function capture(string $stripePaymentIntentId): never
        {
            throw new RuntimeException('Unreachable in this test.');
        }

        public function cancel(string $stripePaymentIntentId, string $reason): void {}

        public function refund(string $stripePaymentIntentId, Money $amount, string $idempotencyKey): void {}
    });

    $event = new AuctionWon(app(ClockInterface::class), $auctionId, $winningBidId, new Money(1500, new Currency('USD')));
    app(TriggerAuctionWinAuthorization::class)->handle($event);

    $paymentIntent = PaymentIntentModel::query()->where('auction_id', $auctionId)->first();
    expect($paymentIntent)->not->toBeNull()
        ->and($paymentIntent->status)->toBe('failed');

    expect(TransferModel::query()->where('auction_id', $auctionId)->exists())->toBeFalse();
});

it('is idempotent: a duplicate AuctionWon delivery for the same auction authorizes only once', function () {
    [$auctionId, $winningBidId, , $buyerId] = createWonAuctionFixture();
    app(BuyerPaymentMethodRepository::class)->save(
        BuyerPaymentMethod::save((string) $buyerId, 'cus_fake', 'pm_fake', app(ClockInterface::class)),
    );

    $authorizeCallCounter = new class implements PaymentAuthorizationGateway
    {
        public int $authorizeCalls = 0;

        public function authorize(string $idempotencyKey, Money $amount, string $stripePaymentMethodId, string $description): AuthorizationAttempt
        {
            $this->authorizeCalls++;

            return AuthorizationAttempt::succeeded('pi_fake_123');
        }

        public function capture(string $stripePaymentIntentId): never
        {
            throw new RuntimeException('Unreachable in this test.');
        }

        public function cancel(string $stripePaymentIntentId, string $reason): void {}

        public function refund(string $stripePaymentIntentId, Money $amount, string $idempotencyKey): void {}
    };
    $this->app->bind(PaymentAuthorizationGateway::class, fn () => $authorizeCallCounter);

    $event = new AuctionWon(app(ClockInterface::class), $auctionId, $winningBidId, new Money(1500, new Currency('USD')));
    app(TriggerAuctionWinAuthorization::class)->handle($event);
    app(TriggerAuctionWinAuthorization::class)->handle($event);

    expect($authorizeCallCounter->authorizeCalls)->toBe(1)
        ->and(PaymentIntentModel::query()->where('auction_id', $auctionId)->count())->toBe(1);
});
