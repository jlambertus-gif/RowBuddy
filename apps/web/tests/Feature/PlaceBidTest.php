<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RowBuddy\Administration\Infrastructure\Eloquent\AccountStandingModel;
use RowBuddy\Auctions\Application\AuctionService;
use RowBuddy\Bids\Infrastructure\Eloquent\BidModel;
use RowBuddy\Bids\Infrastructure\Eloquent\BidPlacementClaimModel;
use RowBuddy\Queues\Infrastructure\Eloquent\QueueModel;
use RowBuddy\SharedKernel\ValueObjects\Currency;
use RowBuddy\SharedKernel\ValueObjects\Money;
use Tests\TestCase;

uses(RefreshDatabase::class);

function createPublishedQueueForBidTest(): string
{
    $id = (string) Str::uuid();

    QueueModel::query()->create([
        'id' => $id,
        'category' => 'concert',
        'jurisdiction_country' => 'US',
        'center_latitude' => 32.7157,
        'center_longitude' => -117.1611,
        'radius_meters' => 200,
        'authorship' => 'admin_curated',
        'organizer_reference' => 'venue-42',
        'status' => 'published',
    ]);

    return $id;
}

/**
 * Drives a seller to Evidence Verified through the real Phase 2 HTTP
 * endpoints, then opens a real Auction through the real AuctionService —
 * the only way to get an auction that survives the real
 * EloquentAuctionGateway's LiveProximityChecker instead of being
 * immediately cancelled for having no backing presence session (mirrors
 * EloquentAuctionGatewayTest's own recipe).
 */
function createOpenAuctionForBidTest(TestCase $test, ?User $seller = null): array
{
    $seller ??= User::factory()->create();
    $queueId = createPublishedQueueForBidTest();

    $sessionId = $test->actingAs($seller)
        ->post('/presence-sessions', ['queue_id' => $queueId])
        ->json('data.id');

    $test->actingAs($seller)->post("/presence-sessions/{$sessionId}/gps-pings", [
        'latitude' => 32.7157,
        'longitude' => -117.1611,
        'accuracy_meters' => 60.0,
    ])->assertOk();

    $test->actingAs($seller)->post("/presence-sessions/{$sessionId}/evidence-photos", [
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

    return [$auctionId, $seller];
}

function placeBidPayload(int $amountMinorUnits = 1100, string $currency = 'USD'): array
{
    return ['amount_minor_units' => $amountMinorUnits, 'currency' => $currency];
}

beforeEach(function () {
    Storage::fake('local');
});

it('redirects unauthenticated users to login and places no bid', function () {
    [$auctionId] = createOpenAuctionForBidTest($this);
    // createOpenAuctionForBidTest() calls actingAs() internally (to drive
    // the seller's own presence-session setup) — clear that before
    // asserting this request is genuinely unauthenticated.
    $this->app['auth']->forgetGuards();

    $response = $this->post("/auctions/{$auctionId}/bids", placeBidPayload(), ['Idempotency-Key' => (string) Str::uuid()]);

    $response->assertRedirect('/login');
    expect(BidModel::count())->toBe(0);
});

it('places a bid for an authenticated bidder and derives bidderId from the session only', function () {
    [$auctionId] = createOpenAuctionForBidTest($this);
    $bidder = User::factory()->create();

    $response = $this->actingAs($bidder)->post(
        "/auctions/{$auctionId}/bids",
        [...placeBidPayload(), 'bidder_id' => 999999, 'seller_id' => 888888],
        ['Idempotency-Key' => (string) Str::uuid()],
    );

    $response->assertCreated();
    $bid = BidModel::query()->first();
    expect((string) $bid->bidder_id)->toBe((string) $bidder->id)
        ->and((string) $bid->bidder_id)->not->toBe('999999');
});

it('rejects a seller bidding on their own auction', function () {
    $seller = User::factory()->create();
    [$auctionId] = createOpenAuctionForBidTest($this, $seller);

    $response = $this->actingAs($seller)->post(
        "/auctions/{$auctionId}/bids",
        placeBidPayload(),
        ['Idempotency-Key' => (string) Str::uuid()],
    );

    $response->assertStatus(422);
    expect(BidModel::count())->toBe(0);
});

it('rejects a bid from a suspended account', function () {
    [$auctionId] = createOpenAuctionForBidTest($this);
    $bidder = User::factory()->create();
    AccountStandingModel::query()->create(['user_id' => $bidder->id, 'state' => 'suspended']);

    $response = $this->actingAs($bidder)->post(
        "/auctions/{$auctionId}/bids",
        placeBidPayload(),
        ['Idempotency-Key' => (string) Str::uuid()],
    );

    $response->assertStatus(403);
    expect(BidModel::count())->toBe(0);
});

it('returns 404 for an unknown auction', function () {
    $bidder = User::factory()->create();

    $response = $this->actingAs($bidder)->post(
        '/auctions/'.(string) Str::uuid().'/bids',
        placeBidPayload(),
        ['Idempotency-Key' => (string) Str::uuid()],
    );

    $response->assertNotFound();
});

it('requires an Idempotency-Key header', function () {
    [$auctionId] = createOpenAuctionForBidTest($this);
    $bidder = User::factory()->create();

    $response = $this->actingAs($bidder)->post("/auctions/{$auctionId}/bids", placeBidPayload());

    $response->assertStatus(400);
    expect(BidModel::count())->toBe(0);
});

it('rejects an invalid amount and currency', function () {
    [$auctionId] = createOpenAuctionForBidTest($this);
    $bidder = User::factory()->create();

    $response = $this->actingAs($bidder)->post(
        "/auctions/{$auctionId}/bids",
        ['amount_minor_units' => 0, 'currency' => 'US'],
        ['Idempotency-Key' => (string) Str::uuid(), 'Accept' => 'application/json'],
    );

    $response->assertStatus(422);
    expect(BidModel::count())->toBe(0);
});

it('preserves deterministic ordering: a second, higher bid wins and a subsequent lower bid is rejected', function () {
    [$auctionId] = createOpenAuctionForBidTest($this);
    $first = User::factory()->create();
    $second = User::factory()->create();
    $third = User::factory()->create();

    $this->actingAs($first)->post("/auctions/{$auctionId}/bids", placeBidPayload(1100), ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated();

    $this->actingAs($second)->post("/auctions/{$auctionId}/bids", placeBidPayload(1200), ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated();

    $this->actingAs($third)->post("/auctions/{$auctionId}/bids", placeBidPayload(1150), ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(422);

    expect(BidModel::query()->count())->toBe(2);
});

it('is idempotent: a duplicate HTTP retry with the same Idempotency-Key returns the same bid without creating a second one', function () {
    [$auctionId] = createOpenAuctionForBidTest($this);
    $bidder = User::factory()->create();
    $idempotencyKey = (string) Str::uuid();

    $first = $this->actingAs($bidder)->post("/auctions/{$auctionId}/bids", placeBidPayload(), ['Idempotency-Key' => $idempotencyKey]);
    $retry = $this->actingAs($bidder)->post("/auctions/{$auctionId}/bids", placeBidPayload(), ['Idempotency-Key' => $idempotencyKey]);

    $first->assertCreated();
    $retry->assertCreated();
    expect($retry->json('data.id'))->toBe($first->json('data.id'))
        ->and(BidModel::query()->count())->toBe(1);
});

it('rejects reusing the same Idempotency-Key for a genuinely different bid request', function () {
    [$auctionId] = createOpenAuctionForBidTest($this);
    $bidder = User::factory()->create();
    $idempotencyKey = (string) Str::uuid();

    $this->actingAs($bidder)->post("/auctions/{$auctionId}/bids", placeBidPayload(1100), ['Idempotency-Key' => $idempotencyKey])
        ->assertCreated();

    $response = $this->actingAs($bidder)->post("/auctions/{$auctionId}/bids", placeBidPayload(1300), ['Idempotency-Key' => $idempotencyKey]);

    $response->assertStatus(422);
    expect(BidModel::query()->count())->toBe(1);
});

it('reports a retryable conflict, explicitly safe to retry with the same key, when a concurrent request is still being processed', function () {
    [$auctionId] = createOpenAuctionForBidTest($this);
    $bidder = User::factory()->create();
    $idempotencyKey = (string) Str::uuid();

    // Simulates a concurrent, still-unresolved claim from an in-flight
    // request carrying the same Idempotency-Key.
    BidPlacementClaimModel::query()->create([
        'bidder_id' => $bidder->id,
        'idempotency_key' => $idempotencyKey,
        'auction_id' => $auctionId,
        'request_fingerprint' => hash('sha256', $auctionId.'|1100|USD'),
    ]);

    $response = $this->actingAs($bidder)->post(
        "/auctions/{$auctionId}/bids",
        placeBidPayload(),
        ['Idempotency-Key' => $idempotencyKey],
    );

    $response->assertStatus(409)
        ->assertJson(['retryable' => true]);
    expect($response->json('message'))->toContain('same Idempotency-Key');
    expect(BidModel::count())->toBe(0);
});

it('does not treat a rate-limited request as a bid attempt and writes no idempotency claim for it', function () {
    [$auctionId] = createOpenAuctionForBidTest($this);
    $bidder = User::factory()->create();

    // The first request succeeds; the fixed amount then falls below the
    // new highest bid, so every subsequent request within the limit is a
    // cheap, deterministic 422 rather than a real accepted bid — only the
    // request *count* against the per-user limiter matters here.
    for ($i = 0; $i < 30; $i++) {
        $this->actingAs($bidder)->post(
            "/auctions/{$auctionId}/bids",
            placeBidPayload(1100),
            ['Idempotency-Key' => (string) Str::uuid()],
        );
    }

    $throttledKey = (string) Str::uuid();
    $response = $this->actingAs($bidder)->post(
        "/auctions/{$auctionId}/bids",
        placeBidPayload(1100),
        ['Idempotency-Key' => $throttledKey],
    );

    $response->assertStatus(429);
    expect(BidPlacementClaimModel::query()->where('idempotency_key', $throttledKey)->exists())->toBeFalse();
});

it('has en and es translations for every auction and bid message key', function () {
    $basePath = dirname(__DIR__, 2);

    foreach (['auctions', 'bids'] as $namespace) {
        $en = require "{$basePath}/lang/en/{$namespace}.php";
        $es = require "{$basePath}/lang/es/{$namespace}.php";

        expect(array_keys($en))->toBe(array_keys($es));
    }
});
