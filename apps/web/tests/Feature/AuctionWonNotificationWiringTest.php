<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RowBuddy\Auctions\Events\AuctionWon;
use RowBuddy\Bids\Bid;
use RowBuddy\Bids\Contracts\BidRepository;
use RowBuddy\Notifications\Mail\AuctionWonMail;
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

it('sends the AuctionWon email end-to-end, rendered in the winner\'s own stored language', function () {
    Mail::fake();

    $winner = User::factory()->create(['language' => 'es']);
    $bidId = (string) Str::uuid();
    $auctionId = (string) Str::uuid();
    $amount = new Money(15000, new Currency('USD'));

    recordRealBid($bidId, $auctionId, (string) $winner->id, $amount);

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

    $event = new AuctionWon(new FrozenClock, $auctionId, $bidId, $amount);
    event($event);
    event($event);

    Mail::assertSent(AuctionWonMail::class, 1);
});
