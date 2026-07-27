<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RowBuddy\QueuePresence\Contracts\EvidenceStorage;
use RowBuddy\QueuePresence\Exceptions\EvidenceStorageFailed;
use RowBuddy\QueuePresence\Infrastructure\Eloquent\EvidencePhotoModel;
use RowBuddy\Queues\Infrastructure\Eloquent\QueueModel;
use Tests\TestCase;

uses(RefreshDatabase::class);

function createPublishedQueueForEvidenceTest(): string
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

function startPresenceSessionForEvidenceTest(TestCase $test, User $user, string $queueId): string
{
    return $test->actingAs($user)
        ->post('/presence-sessions', ['queue_id' => $queueId])
        ->json('data.id');
}

it('uploads an evidence photo for the session owner and stores it privately', function () {
    Storage::fake('local');
    $queueId = createPublishedQueueForEvidenceTest();
    $user = User::factory()->create();
    $sessionId = startPresenceSessionForEvidenceTest($this, $user, $queueId);

    $response = $this->actingAs($user)->post("/presence-sessions/{$sessionId}/evidence-photos", [
        'photo' => UploadedFile::fake()->image('evidence.jpg', 100, 100),
    ]);

    $response->assertCreated();
    $photo = EvidencePhotoModel::query()->where('presence_session_id', $sessionId)->first();
    expect($photo)->not->toBeNull()
        ->and($photo->mime_type)->toBe('image/jpeg')
        ->and($photo->size_bytes)->toBeGreaterThan(0);
    Storage::disk('local')->assertExists($photo->storage_reference);

    // Evidence alone, with no supporting GPS ping, earns no confidence
    // credit at all (ADR-008 §3).
    expect($response->json('data.confidence'))->toBe(['points' => 0, 'tier' => 'unverified']);
});

it('reaches evidence verified once a within-geofence ping and a photo have both been recorded', function () {
    Storage::fake('local');
    $queueId = createPublishedQueueForEvidenceTest();
    $user = User::factory()->create();
    $sessionId = startPresenceSessionForEvidenceTest($this, $user, $queueId);

    $pingResponse = $this->actingAs($user)->post("/presence-sessions/{$sessionId}/gps-pings", [
        'latitude' => 32.7157,
        'longitude' => -117.1611,
        'accuracy_meters' => 60.0,
    ]);
    expect($pingResponse->json('data.confidence'))->toBe(['points' => 40, 'tier' => 'location_verified']);

    $photoResponse = $this->actingAs($user)->post("/presence-sessions/{$sessionId}/evidence-photos", [
        'photo' => UploadedFile::fake()->image('evidence.jpg'),
    ]);
    expect($photoResponse->json('data.confidence'))->toBe(['points' => 140, 'tier' => 'evidence_verified']);

    // Ending the session doesn't recompute — the last-known score still shows.
    $endResponse = $this->actingAs($user)->post("/presence-sessions/{$sessionId}/end");
    expect($endResponse->json('data.confidence'))->toBe(['points' => 140, 'tier' => 'evidence_verified'])
        ->and($endResponse->json('data.status'))->toBe('ended');
});

it('rejects an upload without a photo', function () {
    $queueId = createPublishedQueueForEvidenceTest();
    $user = User::factory()->create();
    $sessionId = startPresenceSessionForEvidenceTest($this, $user, $queueId);

    $response = $this->actingAs($user)->post("/presence-sessions/{$sessionId}/evidence-photos", []);

    $response->assertSessionHasErrors('photo');
});

it('rejects an upload that is not an image', function () {
    $queueId = createPublishedQueueForEvidenceTest();
    $user = User::factory()->create();
    $sessionId = startPresenceSessionForEvidenceTest($this, $user, $queueId);

    $response = $this->actingAs($user)->post("/presence-sessions/{$sessionId}/evidence-photos", [
        'photo' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
    ]);

    $response->assertSessionHasErrors('photo');
});

it('rejects an upload larger than the size limit', function () {
    $queueId = createPublishedQueueForEvidenceTest();
    $user = User::factory()->create();
    $sessionId = startPresenceSessionForEvidenceTest($this, $user, $queueId);

    $response = $this->actingAs($user)->post("/presence-sessions/{$sessionId}/evidence-photos", [
        'photo' => UploadedFile::fake()->create('too-big.jpg', 8193, 'image/jpeg'),
    ]);

    $response->assertSessionHasErrors('photo');
});

it('returns 404 when uploading against an unknown session', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/presence-sessions/'.Str::uuid().'/evidence-photos', [
        'photo' => UploadedFile::fake()->image('evidence.jpg'),
    ]);

    $response->assertStatus(404);
});

it('forbids uploading a photo to another user\'s session', function () {
    $queueId = createPublishedQueueForEvidenceTest();
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $sessionId = startPresenceSessionForEvidenceTest($this, $owner, $queueId);

    $response = $this->actingAs($intruder)->post("/presence-sessions/{$sessionId}/evidence-photos", [
        'photo' => UploadedFile::fake()->image('evidence.jpg'),
    ]);

    $response->assertStatus(403);
    expect(EvidencePhotoModel::query()->where('presence_session_id', $sessionId)->count())->toBe(0);
});

it('rejects uploading a photo once the session has ended', function () {
    $queueId = createPublishedQueueForEvidenceTest();
    $user = User::factory()->create();
    $sessionId = startPresenceSessionForEvidenceTest($this, $user, $queueId);
    $this->actingAs($user)->post("/presence-sessions/{$sessionId}/end")->assertOk();

    $response = $this->actingAs($user)->post("/presence-sessions/{$sessionId}/evidence-photos", [
        'photo' => UploadedFile::fake()->image('evidence.jpg'),
    ]);

    $response->assertStatus(422);
});

it('returns a clean error and records nothing when storage genuinely fails', function () {
    // Found via real-browser acceptance testing (Sprint 7): filesystems.php
    // configures the local disk with 'throw' => false, so a failed write
    // returns false rather than throwing — LocalPrivateEvidenceStorage
    // must check that itself (see EvidenceStorageFailed's docblock).
    $this->app->bind(
        EvidenceStorage::class,
        function () {
            return new class implements EvidenceStorage
            {
                public function store(string $presenceSessionId, string $contents): string
                {
                    throw EvidenceStorageFailed::forPath('simulated-failure');
                }

                public function temporaryUrl(string $reference, DateTimeImmutable $expiresAt): string
                {
                    throw new RuntimeException('not used in this test');
                }
            };
        },
    );

    $queueId = createPublishedQueueForEvidenceTest();
    $user = User::factory()->create();
    $sessionId = startPresenceSessionForEvidenceTest($this, $user, $queueId);

    $response = $this->actingAs($user)->post("/presence-sessions/{$sessionId}/evidence-photos", [
        'photo' => UploadedFile::fake()->image('evidence.jpg'),
    ]);

    $response->assertStatus(500);
    expect(EvidencePhotoModel::query()->where('presence_session_id', $sessionId)->count())->toBe(0);
});

it('returns a temporary signed url for the photo owner', function () {
    Storage::fake('local');
    $queueId = createPublishedQueueForEvidenceTest();
    $user = User::factory()->create();
    $sessionId = startPresenceSessionForEvidenceTest($this, $user, $queueId);
    $photoId = $this->actingAs($user)
        ->post("/presence-sessions/{$sessionId}/evidence-photos", ['photo' => UploadedFile::fake()->image('evidence.jpg')])
        ->json('data.id');

    // recordEvidencePhoto returns the session, not the photo id, so look
    // the photo up directly to exercise the show endpoint with its id.
    $photo = EvidencePhotoModel::query()->where('presence_session_id', $sessionId)->first();

    $response = $this->actingAs($user)->get("/presence-sessions/{$sessionId}/evidence-photos/{$photo->id}");

    $response->assertOk()
        ->assertJsonStructure(['data' => ['url', 'expires_at']]);
});

it('returns 404 for an evidence photo that does not belong to the session', function () {
    Storage::fake('local');
    $queueId = createPublishedQueueForEvidenceTest();
    $user = User::factory()->create();
    $sessionId1 = startPresenceSessionForEvidenceTest($this, $user, $queueId);

    $queueId2 = createPublishedQueueForEvidenceTest();
    $sessionId2 = $this->actingAs($user)->post('/presence-sessions', ['queue_id' => $queueId2])->json('data.id');

    $this->actingAs($user)->post("/presence-sessions/{$sessionId1}/evidence-photos", ['photo' => UploadedFile::fake()->image('evidence.jpg')]);
    $photo = EvidencePhotoModel::query()->where('presence_session_id', $sessionId1)->first();

    $response = $this->actingAs($user)->get("/presence-sessions/{$sessionId2}/evidence-photos/{$photo->id}");

    $response->assertStatus(404);
});

it('forbids retrieving a photo url through another user\'s session', function () {
    Storage::fake('local');
    $queueId = createPublishedQueueForEvidenceTest();
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $sessionId = startPresenceSessionForEvidenceTest($this, $owner, $queueId);
    $this->actingAs($owner)->post("/presence-sessions/{$sessionId}/evidence-photos", ['photo' => UploadedFile::fake()->image('evidence.jpg')]);
    $photo = EvidencePhotoModel::query()->where('presence_session_id', $sessionId)->first();

    $response = $this->actingAs($intruder)->get("/presence-sessions/{$sessionId}/evidence-photos/{$photo->id}");

    $response->assertStatus(403);
});

it('has en and es translations for every evidence-photo message key', function () {
    $keys = [
        'presence.errors.invalid_photo',
        'presence.fields.photo',
    ];

    foreach ($keys as $key) {
        $en = __($key, [], 'en');
        $es = __($key, [], 'es');

        expect($en)->not->toBe($key)
            ->and($es)->not->toBe($key)
            ->and($es)->not->toBe($en);
    }
});
