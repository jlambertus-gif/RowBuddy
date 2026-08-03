<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RowBuddy\Auctions\Infrastructure\Eloquent\AuctionModel;
use RowBuddy\Bids\Infrastructure\Eloquent\BidModel;

uses(RefreshDatabase::class);

function createAuctionForShowTest(array $overrides = []): string
{
    $id = (string) Str::uuid();

    AuctionModel::query()->create([
        'id' => $id,
        'queue_id' => (string) Str::uuid(),
        'seller_id' => 501,
        'presence_session_id' => (string) Str::uuid(),
        'starting_price_minor_units' => 1000,
        'starting_price_currency' => 'USD',
        'opened_at' => now(),
        'closes_at' => now()->addMinutes(30),
        'status' => 'open',
        ...$overrides,
    ]);

    return $id;
}

it('returns the public snapshot for an open auction with no bids yet', function () {
    $auctionId = createAuctionForShowTest();

    $response = $this->get("/auctions/{$auctionId}");

    $response->assertOk();
    expect($response->json('data'))->toMatchArray([
        'id' => $auctionId,
        'status' => 'open',
        'current_price' => ['amount_minor_units' => 1000, 'currency' => 'USD'],
        'minimum_next_amount' => ['amount_minor_units' => 1001, 'currency' => 'USD'],
        'bid_count' => 0,
    ]);
});

it('reflects the highest bid as the current price and computes the next minimum from it', function () {
    $auctionId = createAuctionForShowTest();
    BidModel::query()->create([
        'id' => (string) Str::uuid(),
        'auction_id' => $auctionId,
        'bidder_id' => 601,
        'amount_minor_units' => 1500,
        'amount_currency' => 'USD',
        'placed_at' => now(),
    ]);

    $response = $this->get("/auctions/{$auctionId}");

    $response->assertOk();
    expect($response->json('data.current_price'))->toBe(['amount_minor_units' => 1500, 'currency' => 'USD'])
        ->and($response->json('data.minimum_next_amount'))->toBe(['amount_minor_units' => 1501, 'currency' => 'USD'])
        ->and($response->json('data.bid_count'))->toBe(1);
});

it('never exposes seller id, bidder id, or presence session id', function () {
    $auctionId = createAuctionForShowTest();

    $response = $this->get("/auctions/{$auctionId}");

    $body = $response->json('data');
    expect($body)->not->toHaveKey('seller_id')
        ->and($body)->not->toHaveKey('presence_session_id')
        ->and(json_encode($body))->not->toContain('501');
});

it('does not expose a minimum next amount once the auction is no longer open', function () {
    $auctionId = createAuctionForShowTest(['status' => 'closing']);

    $response = $this->get("/auctions/{$auctionId}");

    $response->assertOk();
    expect($response->json('data.minimum_next_amount'))->toBeNull()
        ->and($response->json('data.status'))->toBe('closing');
});

it('returns 404 for an unknown auction', function () {
    $response = $this->get('/auctions/'.(string) Str::uuid());

    $response->assertNotFound();
});

it('returns 404 for a won auction, since it is no longer publicly discoverable', function () {
    $auctionId = createAuctionForShowTest(['status' => 'won', 'winning_bid_id' => (string) Str::uuid(), 'winning_amount_minor_units' => 1500, 'winning_amount_currency' => 'USD']);

    $response = $this->get("/auctions/{$auctionId}");

    $response->assertNotFound();
});

it('returns 404 for an expired auction', function () {
    $auctionId = createAuctionForShowTest(['status' => 'expired']);

    $response = $this->get("/auctions/{$auctionId}");

    $response->assertNotFound();
});

it('returns 404 for a cancelled auction', function () {
    $auctionId = createAuctionForShowTest(['status' => 'cancelled']);

    $response = $this->get("/auctions/{$auctionId}");

    $response->assertNotFound();
});

it('does not require authentication', function () {
    $auctionId = createAuctionForShowTest();

    $response = $this->get("/auctions/{$auctionId}");

    $response->assertOk();
});
