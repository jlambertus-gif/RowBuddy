<?php

declare(strict_types=1);

use App\Listeners\TriggerTransferInitiation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RowBuddy\Auctions\Infrastructure\Eloquent\AuctionModel;
use RowBuddy\Bids\Infrastructure\Eloquent\BidModel;
use RowBuddy\Payments\Events\PaymentAuthorized;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\ValueObjects\Currency;
use RowBuddy\SharedKernel\ValueObjects\Money;
use RowBuddy\Transfers\Infrastructure\Eloquent\TransferModel;

uses(RefreshDatabase::class);

/**
 * Real User rows are required, not bare integer ids: Notifications'
 * SendTransferIssuedNotification listener also reacts to TransferIssued
 * and needs a real email on file for the recipient.
 */
function createAuthorizedAuctionFixture(): array
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

it('issues a Transfer and caches the plaintext QR token for the buyer to retrieve', function () {
    [$auctionId, $winningBidId, $sellerId, $buyerId] = createAuthorizedAuctionFixture();

    $event = new PaymentAuthorized(app(ClockInterface::class), 'pi-1', $auctionId, $winningBidId, new Money(1500, new Currency('USD')), new Money(150, new Currency('USD')));
    app(TriggerTransferInitiation::class)->handle($event);

    $transfer = TransferModel::query()->where('auction_id', $auctionId)->first();
    expect($transfer)->not->toBeNull()
        ->and((string) $transfer->seller_id)->toBe((string) $sellerId)
        ->and((string) $transfer->buyer_id)->toBe((string) $buyerId)
        ->and($transfer->status)->toBe('issued');

    $cachedToken = Cache::get("transfers.{$transfer->id}.qr_token");
    expect($cachedToken)->not->toBeNull()
        ->and(hash('sha256', $cachedToken))->toBe($transfer->qr_token_hash);
});

it('is idempotent: a duplicate PaymentAuthorized delivery issues no second Transfer and caches nothing further', function () {
    [$auctionId, $winningBidId] = createAuthorizedAuctionFixture();

    $event = new PaymentAuthorized(app(ClockInterface::class), 'pi-1', $auctionId, $winningBidId, new Money(1500, new Currency('USD')), new Money(150, new Currency('USD')));
    app(TriggerTransferInitiation::class)->handle($event);
    $firstTransfer = TransferModel::query()->where('auction_id', $auctionId)->first();
    $firstToken = Cache::get("transfers.{$firstTransfer->id}.qr_token");

    app(TriggerTransferInitiation::class)->handle($event);

    expect(TransferModel::query()->where('auction_id', $auctionId)->count())->toBe(1)
        ->and(Cache::get("transfers.{$firstTransfer->id}.qr_token"))->toBe($firstToken);
});
