<?php

declare(strict_types=1);

use App\Events\AuctionSnapshotBroadcast;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RowBuddy\Auctions\Application\AuctionService;
use RowBuddy\Bids\Events\BidPlaced;
use RowBuddy\Bids\Infrastructure\Eloquent\BidModel;
use RowBuddy\Queues\Infrastructure\Eloquent\QueueModel;
use RowBuddy\SharedKernel\ValueObjects\Currency;
use RowBuddy\SharedKernel\ValueObjects\Money;
use Tests\TestCase;

uses(RefreshDatabase::class);

function createPublishedQueueForBroadcastTest(): string
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
 * Same real, checker-safe recipe as PlaceBidTest's own helper — a
 * directly-inserted Auction row with no backing presence session is
 * immediately cancelled by the real EloquentAuctionGateway's
 * LiveProximityChecker the moment a bid is attempted against it.
 */
function createOpenAuctionForBroadcastTest(TestCase $test): string
{
    $seller = User::factory()->create();
    $queueId = createPublishedQueueForBroadcastTest();

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

    return $auctionId;
}

beforeEach(function () {
    Storage::fake('local');
});

it('broadcasts an allowlisted auction snapshot after an accepted bid commits', function () {
    Event::fake([AuctionSnapshotBroadcast::class]);

    $auctionId = createOpenAuctionForBroadcastTest($this);
    $bidder = User::factory()->create();

    $this->actingAs($bidder)->post(
        "/auctions/{$auctionId}/bids",
        ['amount_minor_units' => 1100, 'currency' => 'USD'],
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertCreated();

    Event::assertDispatched(AuctionSnapshotBroadcast::class, function (AuctionSnapshotBroadcast $event) use ($auctionId) {
        return $event->auctionId === $auctionId
            && $event->snapshot['bid_count'] === 1
            && $event->snapshot['current_price'] === ['amount_minor_units' => 1100, 'currency' => 'USD'];
    });
});

it('never broadcasts bidder or seller identity, or any raw domain-event payload field', function () {
    Event::fake([AuctionSnapshotBroadcast::class]);

    $auctionId = createOpenAuctionForBroadcastTest($this);
    $bidder = User::factory()->create();

    $this->actingAs($bidder)->post(
        "/auctions/{$auctionId}/bids",
        ['amount_minor_units' => 1100, 'currency' => 'USD'],
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertCreated();

    Event::assertDispatched(AuctionSnapshotBroadcast::class, function (AuctionSnapshotBroadcast $event) {
        $keys = array_keys($event->snapshot);
        sort($keys);

        return $keys === ['bid_count', 'closes_at', 'current_price', 'id', 'minimum_next_amount', 'status'];
    });
});

it('never broadcasts an accepted-bid update when a bid is rejected', function () {
    Event::fake([BidPlaced::class, AuctionSnapshotBroadcast::class]);

    $auctionId = createOpenAuctionForBroadcastTest($this);
    $bidder = User::factory()->create();

    $this->actingAs($bidder)->post(
        "/auctions/{$auctionId}/bids",
        ['amount_minor_units' => 500, 'currency' => 'USD'], // below the starting price -> BidTooLow
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertStatus(422);

    expect(BidModel::count())->toBe(0);
    Event::assertNotDispatched(BidPlaced::class);
    Event::assertNotDispatched(AuctionSnapshotBroadcast::class);
});

it('never broadcasts a second accepted-bid update when an HTTP retry replays an already-accepted bid', function () {
    Event::fake([AuctionSnapshotBroadcast::class]);

    $auctionId = createOpenAuctionForBroadcastTest($this);
    $bidder = User::factory()->create();
    $idempotencyKey = (string) Str::uuid();

    $this->actingAs($bidder)->post(
        "/auctions/{$auctionId}/bids",
        ['amount_minor_units' => 1100, 'currency' => 'USD'],
        ['Idempotency-Key' => $idempotencyKey],
    )->assertCreated();

    $this->actingAs($bidder)->post(
        "/auctions/{$auctionId}/bids",
        ['amount_minor_units' => 1100, 'currency' => 'USD'],
        ['Idempotency-Key' => $idempotencyKey],
    )->assertCreated();

    expect(BidModel::count())->toBe(1);
    Event::assertDispatchedTimes(AuctionSnapshotBroadcast::class, 1);
});

it('broadcasts on the public per-auction channel, unauthenticated', function () {
    $auctionId = (string) Str::uuid();
    $event = new AuctionSnapshotBroadcast($auctionId, [
        'id' => $auctionId,
        'status' => 'open',
        'current_price' => ['amount_minor_units' => 1000, 'currency' => 'USD'],
        'minimum_next_amount' => ['amount_minor_units' => 1001, 'currency' => 'USD'],
        'closes_at' => now()->toAtomString(),
        'bid_count' => 0,
    ]);

    expect($event->broadcastOn())->toBeInstanceOf(Channel::class)
        ->and($event->broadcastOn())->not->toBeInstanceOf(PrivateChannel::class)
        ->and($event->broadcastOn()->name)->toBe("auctions.{$auctionId}")
        ->and($event->broadcastAs())->toBe('snapshot.updated');
});
