<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
