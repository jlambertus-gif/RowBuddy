<?php

declare(strict_types=1);

use App\Infrastructure\QueuePresenceSellerVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RowBuddy\Auctions\Contracts\SellerPresenceVerification;
use RowBuddy\Auctions\ValueObjects\ConfidenceTier;
use RowBuddy\Queues\Infrastructure\Eloquent\QueueModel;

uses(RefreshDatabase::class);

function createPublishedQueueForAuctionsVerificationTest(): string
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

it('resolves the real QueuePresenceSellerVerification adapter from the container', function () {
    expect(app(SellerPresenceVerification::class))->toBeInstanceOf(QueuePresenceSellerVerification::class);
});

it('returns null when the seller has no presence session for the queue at all', function () {
    $queueId = createPublishedQueueForAuctionsVerificationTest();
    $user = User::factory()->create();

    $snapshot = app(SellerPresenceVerification::class)->verificationFor((string) $user->id, $queueId);

    expect($snapshot)->toBeNull();
});

it('returns null when a session exists but no signal has been recorded yet', function () {
    $queueId = createPublishedQueueForAuctionsVerificationTest();
    $user = User::factory()->create();

    $this->actingAs($user)->post('/presence-sessions', ['queue_id' => $queueId])->assertCreated();

    $snapshot = app(SellerPresenceVerification::class)->verificationFor((string) $user->id, $queueId);

    expect($snapshot)->toBeNull();
});

it('reports Evidence Verified with the correct fields once a within-geofence ping and a photo are both recorded', function () {
    Storage::fake('local');
    $queueId = createPublishedQueueForAuctionsVerificationTest();
    $user = User::factory()->create();

    $sessionId = $this->actingAs($user)
        ->post('/presence-sessions', ['queue_id' => $queueId])
        ->json('data.id');

    $this->actingAs($user)->post("/presence-sessions/{$sessionId}/gps-pings", [
        'latitude' => 32.7157,
        'longitude' => -117.1611,
        'accuracy_meters' => 60.0,
    ])->assertOk();

    $this->actingAs($user)->post("/presence-sessions/{$sessionId}/evidence-photos", [
        'photo' => UploadedFile::fake()->image('evidence.jpg'),
    ])->assertCreated();

    $snapshot = app(SellerPresenceVerification::class)->verificationFor((string) $user->id, $queueId);

    expect($snapshot)->not->toBeNull()
        ->and($snapshot->tier)->toBe(ConfidenceTier::EvidenceVerified)
        ->and($snapshot->points)->toBe(140)
        ->and($snapshot->sessionActive)->toBeTrue()
        ->and($snapshot->lastWithinGeofencePingAt)->not->toBeNull();
});

it('reports sessionActive as false once the session has ended, while keeping the last-known tier', function () {
    Storage::fake('local');
    $queueId = createPublishedQueueForAuctionsVerificationTest();
    $user = User::factory()->create();

    $sessionId = $this->actingAs($user)
        ->post('/presence-sessions', ['queue_id' => $queueId])
        ->json('data.id');

    $this->actingAs($user)->post("/presence-sessions/{$sessionId}/gps-pings", [
        'latitude' => 32.7157,
        'longitude' => -117.1611,
        'accuracy_meters' => 60.0,
    ])->assertOk();

    $this->actingAs($user)->post("/presence-sessions/{$sessionId}/evidence-photos", [
        'photo' => UploadedFile::fake()->image('evidence.jpg'),
    ])->assertCreated();

    $this->actingAs($user)->post("/presence-sessions/{$sessionId}/end")->assertOk();

    $snapshot = app(SellerPresenceVerification::class)->verificationFor((string) $user->id, $queueId);

    expect($snapshot)->not->toBeNull()
        ->and($snapshot->tier)->toBe(ConfidenceTier::EvidenceVerified)
        ->and($snapshot->sessionActive)->toBeFalse();
});

it('returns null for a different seller who has no presence session of their own', function () {
    Storage::fake('local');
    $queueId = createPublishedQueueForAuctionsVerificationTest();
    $sellerWithSession = User::factory()->create();
    $otherSeller = User::factory()->create();

    $this->actingAs($sellerWithSession)->post('/presence-sessions', ['queue_id' => $queueId])->assertCreated();

    $snapshot = app(SellerPresenceVerification::class)->verificationFor((string) $otherSeller->id, $queueId);

    expect($snapshot)->toBeNull();
});
