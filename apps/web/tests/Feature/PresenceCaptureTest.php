<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RowBuddy\QueuePresence\Infrastructure\Eloquent\GpsPingModel;
use RowBuddy\QueuePresence\Infrastructure\Eloquent\PresenceSessionModel;
use RowBuddy\Queues\Infrastructure\Eloquent\QueueModel;

uses(RefreshDatabase::class);

function createPublishedQueueForPresenceTest(): string
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

it('redirects unauthenticated users to login and starts no session', function () {
    $response = $this->post('/presence-sessions', ['queue_id' => createPublishedQueueForPresenceTest()]);

    $response->assertRedirect('/login');
    expect(PresenceSessionModel::count())->toBe(0);
});

it('starts a presence session for an authenticated user against a published queue', function () {
    $queueId = createPublishedQueueForPresenceTest();
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/presence-sessions', ['queue_id' => $queueId]);

    $response->assertCreated();
    $session = PresenceSessionModel::query()->first();
    expect($session)->not->toBeNull()
        ->and($session->queue_id)->toBe($queueId)
        ->and((string) $session->seller_id)->toBe((string) $user->id)
        ->and($session->status)->toBe('active');
});

it('rejects starting a session against a queue that is not published', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/presence-sessions', ['queue_id' => (string) Str::uuid()]);

    $response->assertStatus(422);
    expect(PresenceSessionModel::count())->toBe(0);
});

it('rejects starting a second active session for the same seller and queue', function () {
    $queueId = createPublishedQueueForPresenceTest();
    $user = User::factory()->create();

    $this->actingAs($user)->post('/presence-sessions', ['queue_id' => $queueId])->assertCreated();
    $response = $this->actingAs($user)->post('/presence-sessions', ['queue_id' => $queueId]);

    $response->assertStatus(422);
    expect(PresenceSessionModel::count())->toBe(1);
});

it('rejects an invalid start request', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/presence-sessions', ['queue_id' => 'not-a-uuid']);

    $response->assertSessionHasErrors('queue_id');
});

it('records a GPS ping within the geofence for the session owner', function () {
    $queueId = createPublishedQueueForPresenceTest();
    $user = User::factory()->create();
    $sessionId = $this->actingAs($user)
        ->post('/presence-sessions', ['queue_id' => $queueId])
        ->json('data.id');

    $response = $this->actingAs($user)->post("/presence-sessions/{$sessionId}/gps-pings", [
        'latitude' => 32.7157,
        'longitude' => -117.1611,
        'accuracy_meters' => 12.5,
    ]);

    $response->assertOk();
    $ping = GpsPingModel::query()->where('presence_session_id', $sessionId)->first();
    expect($ping)->not->toBeNull()
        ->and($ping->within_geofence)->toBeTrue();
});

it('records a GPS ping outside the geofence as such', function () {
    $queueId = createPublishedQueueForPresenceTest();
    $user = User::factory()->create();
    $sessionId = $this->actingAs($user)
        ->post('/presence-sessions', ['queue_id' => $queueId])
        ->json('data.id');

    $this->actingAs($user)->post("/presence-sessions/{$sessionId}/gps-pings", [
        'latitude' => 0,
        'longitude' => 0,
        'accuracy_meters' => 12.5,
    ])->assertOk();

    $ping = GpsPingModel::query()->where('presence_session_id', $sessionId)->first();
    expect($ping->within_geofence)->toBeFalse();
});

it('returns 404 when recording a ping against an unknown session', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/presence-sessions/'.Str::uuid().'/gps-pings', [
        'latitude' => 0,
        'longitude' => 0,
        'accuracy_meters' => 12.5,
    ]);

    $response->assertStatus(404);
});

it('forbids recording a ping on another user\'s session', function () {
    $queueId = createPublishedQueueForPresenceTest();
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $sessionId = $this->actingAs($owner)
        ->post('/presence-sessions', ['queue_id' => $queueId])
        ->json('data.id');

    $response = $this->actingAs($intruder)->post("/presence-sessions/{$sessionId}/gps-pings", [
        'latitude' => 32.7157,
        'longitude' => -117.1611,
        'accuracy_meters' => 12.5,
    ]);

    $response->assertStatus(403);
    expect(GpsPingModel::query()->where('presence_session_id', $sessionId)->count())->toBe(0);
});

it('rejects an invalid GPS ping request', function () {
    $queueId = createPublishedQueueForPresenceTest();
    $user = User::factory()->create();
    $sessionId = $this->actingAs($user)
        ->post('/presence-sessions', ['queue_id' => $queueId])
        ->json('data.id');

    $response = $this->actingAs($user)->post("/presence-sessions/{$sessionId}/gps-pings", [
        'latitude' => 200,
        'longitude' => -117.1611,
        'accuracy_meters' => -1,
    ]);

    $response->assertSessionHasErrors(['latitude', 'accuracy_meters']);
});

it('ends a presence session for its owner', function () {
    $queueId = createPublishedQueueForPresenceTest();
    $user = User::factory()->create();
    $sessionId = $this->actingAs($user)
        ->post('/presence-sessions', ['queue_id' => $queueId])
        ->json('data.id');

    $response = $this->actingAs($user)->post("/presence-sessions/{$sessionId}/end");

    $response->assertOk();
    expect(PresenceSessionModel::query()->find($sessionId)->status)->toBe('ended');
});

it('returns 404 when ending an unknown session', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/presence-sessions/'.Str::uuid().'/end');

    $response->assertStatus(404);
});

it('forbids ending another user\'s session', function () {
    $queueId = createPublishedQueueForPresenceTest();
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $sessionId = $this->actingAs($owner)
        ->post('/presence-sessions', ['queue_id' => $queueId])
        ->json('data.id');

    $response = $this->actingAs($intruder)->post("/presence-sessions/{$sessionId}/end");

    $response->assertStatus(403);
    expect(PresenceSessionModel::query()->find($sessionId)->status)->toBe('active');
});

it('rejects ending a session that has already ended', function () {
    $queueId = createPublishedQueueForPresenceTest();
    $user = User::factory()->create();
    $sessionId = $this->actingAs($user)
        ->post('/presence-sessions', ['queue_id' => $queueId])
        ->json('data.id');

    $this->actingAs($user)->post("/presence-sessions/{$sessionId}/end")->assertOk();
    $response = $this->actingAs($user)->post("/presence-sessions/{$sessionId}/end");

    $response->assertStatus(422);
});

it('has en and es translations for every presence message key', function () {
    $keys = [
        'presence.errors.not_found',
        'presence.errors.access_denied',
        'presence.errors.queue_unavailable',
        'presence.errors.duplicate_active_session',
        'presence.errors.not_active',
        'presence.fields.queue_id',
    ];

    foreach ($keys as $key) {
        $en = __($key, [], 'en');
        $es = __($key, [], 'es');

        expect($en)->not->toBe($key)
            ->and($es)->not->toBe($key)
            ->and($es)->not->toBe($en);
    }
});
