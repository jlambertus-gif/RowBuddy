<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RowBuddy\Auctions\Application\AuctionService;
use RowBuddy\Queues\Infrastructure\Eloquent\QueueModel;
use RowBuddy\SharedKernel\ValueObjects\Currency;
use RowBuddy\SharedKernel\ValueObjects\Money;

uses(RefreshDatabase::class);

/**
 * Mobile Sprint 2. This route is a thin auth:sanctum mirror of the
 * existing web session-guarded /auctions/{id}/bids route, reusing
 * PlaceBidController verbatim (routes/api.php) — every domain-rule
 * branch (bid-too-low, seller-self-bid, suspended-account, etc.) is
 * already covered by tests/Feature/PlaceBidTest.php against that
 * existing route, since it is the identical controller/service. These
 * tests cover only what is new here: the sanctum-guarded wiring itself.
 */
it('places a bid via a Sanctum token and returns the documented response shape (feature, contract)', function () {
    $seller = User::factory()->create();
    $queueId = (string) Str::uuid();
    QueueModel::query()->create([
        'id' => $queueId,
        'category' => 'concert',
        'jurisdiction_country' => 'US',
        'center_latitude' => 32.7157,
        'center_longitude' => -117.1611,
        'radius_meters' => 200,
        'authorship' => 'admin_curated',
        'organizer_reference' => 'venue-42',
        'status' => 'published',
    ]);

    $sessionId = $this->actingAs($seller)
        ->post('/presence-sessions', ['queue_id' => $queueId])
        ->json('data.id');
    $this->actingAs($seller)->post("/presence-sessions/{$sessionId}/gps-pings", [
        'latitude' => 32.7157,
        'longitude' => -117.1611,
        'accuracy_meters' => 60.0,
    ])->assertOk();
    $this->actingAs($seller)->post("/presence-sessions/{$sessionId}/evidence-photos", [
        'photo' => UploadedFile::fake()->image('evidence.jpg'),
    ])->assertCreated();

    $auctionId = (string) Str::uuid();
    app(AuctionService::class)->open(
        $auctionId,
        $queueId,
        (string) $seller->id,
        $sessionId,
        new Money(1000, new Currency('USD')),
    );

    // actingAs() leaves the web session guard logged in as the seller
    // for the remainder of this test — Sanctum's guard, given a choice,
    // prefers an active session over the bearer token, so without this
    // the bid below would be silently attributed to the seller, not the
    // bidder, and rejected as "cannot bid on your own auction." A real
    // mobile request never carries a session cookie at all; this is a
    // test-construction artifact, not a production behavior.
    $this->app['auth']->guard('web')->logout();
    $this->app['auth']->forgetGuards();

    $bidder = User::factory()->create();
    $token = $bidder->createToken('device')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson("/api/v1/auctions/{$auctionId}/bids", [
            'amount_minor_units' => 1100,
            'currency' => 'USD',
        ]);

    $response->assertCreated();
    $response->assertJsonStructure([
        'data' => ['id', 'auction_id', 'amount' => ['amount_minor_units', 'currency'], 'placed_at'],
    ]);
    expect($response->json('data.auction_id'))->toBe($auctionId)
        ->and($response->json('data.amount.amount_minor_units'))->toBe(1100);
});

it('rejects an unauthenticated bid attempt (authorization)', function () {
    $this->postJson('/api/v1/auctions/'.(string) Str::uuid().'/bids', [
        'amount_minor_units' => 1100,
        'currency' => 'USD',
    ])->assertUnauthorized();
});

it('rejects a bid from an unverified authenticated user (authorization)', function () {
    $unverified = User::factory()->unverified()->create();
    $token = $unverified->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/auctions/'.(string) Str::uuid().'/bids', [
            'amount_minor_units' => 1100,
            'currency' => 'USD',
        ])
        ->assertForbidden();
});

it('always attributes the bid to whichever token authenticated the request, never a client-supplied identity (IDOR)', function () {
    $seller = User::factory()->create();
    $queueId = (string) Str::uuid();
    QueueModel::query()->create([
        'id' => $queueId,
        'category' => 'concert',
        'jurisdiction_country' => 'US',
        'center_latitude' => 32.7157,
        'center_longitude' => -117.1611,
        'radius_meters' => 200,
        'authorship' => 'admin_curated',
        'organizer_reference' => 'venue-42',
        'status' => 'published',
    ]);

    $sessionId = $this->actingAs($seller)
        ->post('/presence-sessions', ['queue_id' => $queueId])
        ->json('data.id');
    $this->actingAs($seller)->post("/presence-sessions/{$sessionId}/gps-pings", [
        'latitude' => 32.7157,
        'longitude' => -117.1611,
        'accuracy_meters' => 60.0,
    ])->assertOk();
    $this->actingAs($seller)->post("/presence-sessions/{$sessionId}/evidence-photos", [
        'photo' => UploadedFile::fake()->image('evidence.jpg'),
    ])->assertCreated();

    $auctionId = (string) Str::uuid();
    app(AuctionService::class)->open(
        $auctionId,
        $queueId,
        (string) $seller->id,
        $sessionId,
        new Money(1000, new Currency('USD')),
    );

    // See the identical note in the first test above.
    $this->app['auth']->guard('web')->logout();
    $this->app['auth']->forgetGuards();

    $realBidder = User::factory()->create();
    $impersonatedTarget = User::factory()->create();
    $token = $realBidder->createToken('device')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson("/api/v1/auctions/{$auctionId}/bids", [
            'amount_minor_units' => 1100,
            'currency' => 'USD',
            // PlaceBidRequest has no bidder-identity field at all — this
            // is deliberately extra, unexpected input, proving it is
            // silently ignored rather than somehow attributing the bid
            // to a different user.
            'bidder_id' => (string) $impersonatedTarget->id,
        ]);

    $response->assertCreated();
    $this->assertDatabaseHas('bids', [
        'auction_id' => $auctionId,
        'bidder_id' => (string) $realBidder->id,
    ]);
    $this->assertDatabaseMissing('bids', [
        'auction_id' => $auctionId,
        'bidder_id' => (string) $impersonatedTarget->id,
    ]);
});
