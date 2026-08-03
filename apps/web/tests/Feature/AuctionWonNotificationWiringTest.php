<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RowBuddy\Auctions\Events\AuctionWon;
use RowBuddy\Auctions\Infrastructure\Eloquent\AuctionModel;
use RowBuddy\Bids\Bid;
use RowBuddy\Bids\Contracts\BidRepository;
use RowBuddy\Notifications\Mail\AuctionWonMail;
use RowBuddy\Payments\BuyerPaymentMethod;
use RowBuddy\Payments\Contracts\BuyerPaymentMethodRepository;
use RowBuddy\Payments\Contracts\PaymentAuthorizationGateway;
use RowBuddy\Payments\ValueObjects\AuthorizationAttempt;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Support\FrozenClock;
use RowBuddy\SharedKernel\ValueObjects\Currency;
use RowBuddy\SharedKernel\ValueObjects\Money;

uses(RefreshDatabase::class);

function recordRealBid(string $bidId, string $auctionId, string $bidderId, Money $amount): void
{
    $bid = Bid::place($bidId, $auctionId, $bidderId, $amount, app(ClockInterface::class));
    $bid->releaseEvents();
    app(BidRepository::class)->record($bid);
}

/**
 * Phase 9 (ADR-027 Sprint 3) gave AuctionWon a second real consumer,
 * TriggerAuctionWinAuthorization — every test that fires a real AuctionWon
 * event now needs a complete, production-realistic fixture (a real
 * Auction row, and a saved BuyerPaymentMethod for the winner) so that
 * consumer does not fail loudly for data this test never intended to
 * exercise. The Stripe gateway itself is stubbed so no real network call
 * is attempted.
 */
function completeAuctionWonFixtureFor(string $auctionId, string $winnerId): void
{
    AuctionModel::query()->create([
        'id' => $auctionId,
        'queue_id' => (string) Str::uuid(),
        'seller_id' => User::factory()->create()->id,
        'presence_session_id' => (string) Str::uuid(),
        'starting_price_minor_units' => 1000,
        'starting_price_currency' => 'USD',
        'opened_at' => now(),
        'closes_at' => now()->addMinutes(30),
        'status' => 'won',
    ]);

    app(BuyerPaymentMethodRepository::class)->save(
        BuyerPaymentMethod::save($winnerId, 'cus_fake', 'pm_fake', app(ClockInterface::class)),
    );

    app()->bind(PaymentAuthorizationGateway::class, fn () => new class implements PaymentAuthorizationGateway
    {
        public function authorize(string $idempotencyKey, Money $amount, string $stripePaymentMethodId, string $description): AuthorizationAttempt
        {
            return AuthorizationAttempt::succeeded('pi_fake_notification_test');
        }

        public function capture(string $stripePaymentIntentId): never
        {
            throw new RuntimeException('Unreachable in this test.');
        }

        public function cancel(string $stripePaymentIntentId, string $reason): void {}

        public function refund(string $stripePaymentIntentId, Money $amount, string $idempotencyKey): void {}
    });
}

it('sends the AuctionWon email end-to-end, rendered in the winner\'s own stored language', function () {
    Mail::fake();

    $winner = User::factory()->create(['language' => 'es']);
    $bidId = (string) Str::uuid();
    $auctionId = (string) Str::uuid();
    $amount = new Money(15000, new Currency('USD'));

    recordRealBid($bidId, $auctionId, (string) $winner->id, $amount);
    completeAuctionWonFixtureFor($auctionId, (string) $winner->id);

    event(new AuctionWon(new FrozenClock, $auctionId, $bidId, $amount));

    Mail::assertSent(AuctionWonMail::class, 1);
    Mail::assertSent(AuctionWonMail::class, function (AuctionWonMail $mail) use ($winner) {
        return $mail->hasTo($winner->email) && $mail->locale === 'es';
    });
});

it('falls back to English when the winner has no stored language preference', function () {
    Mail::fake();

    $winner = User::factory()->create();
    $bidId = (string) Str::uuid();
    $auctionId = (string) Str::uuid();
    $amount = new Money(15000, new Currency('USD'));

    recordRealBid($bidId, $auctionId, (string) $winner->id, $amount);
    completeAuctionWonFixtureFor($auctionId, (string) $winner->id);

    event(new AuctionWon(new FrozenClock, $auctionId, $bidId, $amount));

    Mail::assertSent(AuctionWonMail::class, function (AuctionWonMail $mail) {
        return $mail->locale === 'en';
    });
});

it('does not send the AuctionWon email twice when the same event is delivered twice', function () {
    Mail::fake();

    $winner = User::factory()->create();
    $bidId = (string) Str::uuid();
    $auctionId = (string) Str::uuid();
    $amount = new Money(15000, new Currency('USD'));

    recordRealBid($bidId, $auctionId, (string) $winner->id, $amount);
    completeAuctionWonFixtureFor($auctionId, (string) $winner->id);

    $event = new AuctionWon(new FrozenClock, $auctionId, $bidId, $amount);
    event($event);
    event($event);

    Mail::assertSent(AuctionWonMail::class, 1);
});
