<?php

declare(strict_types=1);

use App\Infrastructure\EloquentAuctionGateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RowBuddy\Auctions\Application\AuctionService;
use RowBuddy\Auctions\Events\AuctionCancelled;
use RowBuddy\Auctions\Events\AuctionProximityAtRisk;
use RowBuddy\Bids\Contracts\AuctionGateway;
use RowBuddy\Queues\Infrastructure\Eloquent\QueueModel;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Support\FrozenClock;
use RowBuddy\SharedKernel\ValueObjects\Currency;
use RowBuddy\SharedKernel\ValueObjects\Money;
use Tests\TestCase;

uses(RefreshDatabase::class);

function createPublishedQueueForGatewayTest(): string
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
 * Drives a seller to Evidence Verified via the real, already-built Phase 2
 * HTTP endpoints (same recipe as AuctionsPresenceVerificationTest), then
 * opens a real Auction through the real AuctionService (Sprint 3). Returns
 * [queueId, sellerId, sessionId, auctionId].
 */
function anOpenAuctionForGatewayTest(TestCase $test, User $user, string $queueId): array
{
    $sessionId = $test->actingAs($user)
        ->post('/presence-sessions', ['queue_id' => $queueId])
        ->json('data.id');

    $test->actingAs($user)->post("/presence-sessions/{$sessionId}/gps-pings", [
        'latitude' => 32.7157,
        'longitude' => -117.1611,
        'accuracy_meters' => 60.0,
    ])->assertOk();

    $test->actingAs($user)->post("/presence-sessions/{$sessionId}/evidence-photos", [
        'photo' => UploadedFile::fake()->image('evidence.jpg'),
    ])->assertCreated();

    $auctionId = (string) Str::uuid();
    app(AuctionService::class)->open(
        $auctionId,
        $queueId,
        (string) $user->id,
        $sessionId,
        new Money(1000, new Currency('USD')),
    );

    return [$queueId, (string) $user->id, $sessionId, $auctionId];
}

it('resolves the real EloquentAuctionGateway adapter from the container', function () {
    expect(app(AuctionGateway::class))->toBeInstanceOf(EloquentAuctionGateway::class);
});

it('returns null when the auction does not exist', function () {
    $result = app(AuctionGateway::class)->lockAndCheckForBidding((string) Str::uuid());

    expect($result->snapshot)->toBeNull()
        ->and($result->proximityEvents)->toBe([]);
});

it('reports a fresh, open auction with no proximity events', function () {
    Storage::fake('local');
    $queueId = createPublishedQueueForGatewayTest();
    $user = User::factory()->create();
    [, $sellerId, , $auctionId] = anOpenAuctionForGatewayTest($this, $user, $queueId);

    $result = app(AuctionGateway::class)->lockAndCheckForBidding($auctionId);

    expect($result->snapshot)->not->toBeNull()
        ->and($result->snapshot->sellerId)->toBe($sellerId)
        ->and($result->snapshot->startingPrice->equals(new Money(1000, new Currency('USD'))))->toBeTrue()
        ->and($result->snapshot->isOpenForBidding)->toBeTrue()
        ->and($result->proximityEvents)->toBe([]);
});

it('flags the auction at risk once the ping is stale, while it remains open for bidding', function () {
    Storage::fake('local');
    $queueId = createPublishedQueueForGatewayTest();
    $user = User::factory()->create();
    [, , , $auctionId] = anOpenAuctionForGatewayTest($this, $user, $queueId);

    // Advance the clock 20 minutes past the ping recorded moments ago —
    // past ADR-011's 15-minute staleness threshold.
    app()->instance(ClockInterface::class, new FrozenClock(now()->addMinutes(20)->toDateTimeImmutable()));

    $result = app(AuctionGateway::class)->lockAndCheckForBidding($auctionId);

    expect($result->snapshot->isOpenForBidding)->toBeTrue()
        ->and($result->proximityEvents)->toHaveCount(1)
        ->and($result->proximityEvents[0])->toBeInstanceOf(AuctionProximityAtRisk::class);
});

it('reports the auction as no longer open for bidding once the session has ended', function () {
    Storage::fake('local');
    $queueId = createPublishedQueueForGatewayTest();
    $user = User::factory()->create();
    [, , $sessionId, $auctionId] = anOpenAuctionForGatewayTest($this, $user, $queueId);

    $this->actingAs($user)->post("/presence-sessions/{$sessionId}/end")->assertOk();

    $result = app(AuctionGateway::class)->lockAndCheckForBidding($auctionId);

    expect($result->snapshot->isOpenForBidding)->toBeFalse()
        ->and($result->proximityEvents)->toHaveCount(1)
        ->and($result->proximityEvents[0])->toBeInstanceOf(AuctionCancelled::class);
});
