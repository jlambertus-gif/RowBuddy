<?php

declare(strict_types=1);

use App\Infrastructure\EloquentAuctionGateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RowBuddy\Auctions\Application\AuctionService;
use RowBuddy\Auctions\Contracts\AuctionRepository;
use RowBuddy\Auctions\Events\AuctionCancelled;
use RowBuddy\Auctions\Events\AuctionClosingStarted;
use RowBuddy\Auctions\Events\AuctionExpired;
use RowBuddy\Auctions\Events\AuctionProximityAtRisk;
use RowBuddy\Auctions\Events\AuctionWon;
use RowBuddy\Auctions\ValueObjects\AuctionStatus;
use RowBuddy\Bids\Application\BidService;
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
        ->and($result->events)->toBe([]);
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
        ->and($result->events)->toBe([]);
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
        ->and($result->events)->toHaveCount(1)
        ->and($result->events[0])->toBeInstanceOf(AuctionProximityAtRisk::class);
});

it('reports the auction as no longer open for bidding once the session has ended', function () {
    Storage::fake('local');
    $queueId = createPublishedQueueForGatewayTest();
    $user = User::factory()->create();
    [, , $sessionId, $auctionId] = anOpenAuctionForGatewayTest($this, $user, $queueId);

    $this->actingAs($user)->post("/presence-sessions/{$sessionId}/end")->assertOk();

    $result = app(AuctionGateway::class)->lockAndCheckForBidding($auctionId);

    expect($result->snapshot->isOpenForBidding)->toBeFalse()
        ->and($result->events)->toHaveCount(1)
        ->and($result->events[0])->toBeInstanceOf(AuctionCancelled::class);
});

it('closes and selects the winning bid once the deadline has passed, via the real Bids-backed WinningBidLookup', function () {
    Storage::fake('local');
    $queueId = createPublishedQueueForGatewayTest();
    $seller = User::factory()->create();
    [, , , $auctionId] = anOpenAuctionForGatewayTest($this, $seller, $queueId);

    $bidder = User::factory()->create();
    $bid = app(BidService::class)->place((string) Str::uuid(), $auctionId, (string) $bidder->id, new Money(1500, new Currency('USD')));

    // Past the 30-minute MVP default duration (ADR-013 §1) — the auction
    // is now due to close. Computed relative to the auction's own
    // closesAt, not wall-clock "now", so this cannot drift.
    $closesAt = app(AuctionRepository::class)->findById($auctionId)->closesAt();
    app()->instance(ClockInterface::class, new FrozenClock($closesAt->modify('+1 second')));

    $result = app(AuctionGateway::class)->lockAndCheckForBidding($auctionId);

    // Jumping the clock 31 minutes forward to pass the closing deadline
    // also makes the seller's earlier GPS ping stale relative to the new
    // "now" — LiveProximityChecker correctly flags that too, as its own,
    // separate side effect, before closing evaluation runs.
    expect($result->snapshot->isOpenForBidding)->toBeFalse()
        ->and($result->events)->toHaveCount(3)
        ->and($result->events[0])->toBeInstanceOf(AuctionProximityAtRisk::class)
        ->and($result->events[1])->toBeInstanceOf(AuctionClosingStarted::class)
        ->and($result->events[2])->toBeInstanceOf(AuctionWon::class);

    $persisted = app(AuctionRepository::class)->findById($auctionId);
    expect($persisted->status())->toBe(AuctionStatus::Won)
        ->and($persisted->winningBidId())->toBe($bid->id);
});

it('closes and expires once the deadline has passed with no bids', function () {
    Storage::fake('local');
    $queueId = createPublishedQueueForGatewayTest();
    $seller = User::factory()->create();
    [, , , $auctionId] = anOpenAuctionForGatewayTest($this, $seller, $queueId);

    $closesAt = app(AuctionRepository::class)->findById($auctionId)->closesAt();
    app()->instance(ClockInterface::class, new FrozenClock($closesAt->modify('+1 second')));

    $result = app(AuctionGateway::class)->lockAndCheckForBidding($auctionId);

    // Same interaction as the winning-bid test above: the clock jump also
    // makes the seller's GPS ping stale, so LiveProximityChecker's own
    // event fires first, ahead of the closing/expiry transition.
    expect($result->snapshot->isOpenForBidding)->toBeFalse()
        ->and($result->events)->toHaveCount(3)
        ->and($result->events[0])->toBeInstanceOf(AuctionProximityAtRisk::class)
        ->and($result->events[1])->toBeInstanceOf(AuctionClosingStarted::class)
        ->and($result->events[2])->toBeInstanceOf(AuctionExpired::class);

    expect(app(AuctionRepository::class)->findById($auctionId)->status())->toBe(AuctionStatus::Expired);
});

it('extends the deadline end-to-end when a real bid lands inside the soft-close window', function () {
    Storage::fake('local');
    $queueId = createPublishedQueueForGatewayTest();
    $seller = User::factory()->create();
    [, , , $auctionId] = anOpenAuctionForGatewayTest($this, $seller, $queueId);

    $originalClosesAt = app(AuctionRepository::class)->findById($auctionId)->closesAt();

    // Inside the last 2 minutes of the 30-minute default duration
    // (ADR-013 §2's soft-close window) — computed relative to the
    // auction's own closesAt, not wall-clock "now", so this cannot drift
    // across the window boundary due to test-execution timing.
    app()->instance(ClockInterface::class, new FrozenClock($originalClosesAt->modify('-1 minute')));

    $bidder = User::factory()->create();
    app(BidService::class)->place((string) Str::uuid(), $auctionId, (string) $bidder->id, new Money(1500, new Currency('USD')));

    $extendedClosesAt = app(AuctionRepository::class)->findById($auctionId)->closesAt();

    expect($extendedClosesAt)->toEqual($originalClosesAt->modify('+2 minutes'))
        ->and($extendedClosesAt)->not->toEqual($originalClosesAt);
});
