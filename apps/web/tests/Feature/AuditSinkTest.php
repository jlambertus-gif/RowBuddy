<?php

declare(strict_types=1);

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RowBuddy\Queues\Infrastructure\Eloquent\JurisdictionRuleModel;
use RowBuddy\Queues\Infrastructure\Eloquent\QueueModel;

uses(RefreshDatabase::class);

function createPublishedQueueForAuditTest(): string
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

it('audits a presence session being started', function () {
    $queueId = createPublishedQueueForAuditTest();
    $user = User::factory()->create();

    $sessionId = $this->actingAs($user)
        ->post('/presence-sessions', ['queue_id' => $queueId])
        ->json('data.id');

    $audit = AuditEvent::query()->where('event_name', 'queue_presence.presence_session_started')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->subject_type)->toBe('presence_session')
        ->and($audit->subject_id)->toBe($sessionId)
        ->and($audit->payload['seller_id'])->toBe((string) $user->id);
});

it('does not audit an individual GPS ping', function () {
    $queueId = createPublishedQueueForAuditTest();
    $user = User::factory()->create();
    $sessionId = $this->actingAs($user)->post('/presence-sessions', ['queue_id' => $queueId])->json('data.id');

    $this->actingAs($user)->post("/presence-sessions/{$sessionId}/gps-pings", [
        'latitude' => 32.7157,
        'longitude' => -117.1611,
        'accuracy_meters' => 12.5,
    ]);

    expect(AuditEvent::query()->where('event_name', 'queue_presence.gps_ping_recorded')->count())->toBe(0);
});

it('audits an evidence photo being recorded', function () {
    $queueId = createPublishedQueueForAuditTest();
    $user = User::factory()->create();
    $sessionId = $this->actingAs($user)->post('/presence-sessions', ['queue_id' => $queueId])->json('data.id');

    $this->actingAs($user)->post("/presence-sessions/{$sessionId}/evidence-photos", [
        'photo' => UploadedFile::fake()->image('evidence.jpg'),
    ]);

    $audit = AuditEvent::query()->where('event_name', 'queue_presence.evidence_photo_recorded')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->subject_id)->toBe($sessionId);
});

it('audits a presence session ending', function () {
    $queueId = createPublishedQueueForAuditTest();
    $user = User::factory()->create();
    $sessionId = $this->actingAs($user)->post('/presence-sessions', ['queue_id' => $queueId])->json('data.id');

    $this->actingAs($user)->post("/presence-sessions/{$sessionId}/end");

    $audit = AuditEvent::query()->where('event_name', 'queue_presence.presence_session_ended')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->subject_id)->toBe($sessionId);
});

it('audits a confidence tier transition but not a same-tier recomputation', function () {
    $queueId = createPublishedQueueForAuditTest();
    $user = User::factory()->create();
    $sessionId = $this->actingAs($user)->post('/presence-sessions', ['queue_id' => $queueId])->json('data.id');

    // First within-geofence ping: 0 -> Location Verified (40 points). Materially relevant.
    $this->actingAs($user)->post("/presence-sessions/{$sessionId}/gps-pings", [
        'latitude' => 32.7157,
        'longitude' => -117.1611,
        'accuracy_meters' => 60.0, // no accuracy bonus, isolates the tier-crossing case
    ]);

    expect(AuditEvent::query()->where('event_name', 'queue_presence.presence_confidence_computed')->count())->toBe(1);

    // A second, still-within-Location-Verified ping (better accuracy, same tier). Not materially relevant.
    $this->actingAs($user)->post("/presence-sessions/{$sessionId}/gps-pings", [
        'latitude' => 32.7157,
        'longitude' => -117.1611,
        'accuracy_meters' => 15.0,
    ]);

    expect(AuditEvent::query()->where('event_name', 'queue_presence.presence_confidence_computed')->count())->toBe(1);

    // Evidence photo pushes it into Evidence Verified. Materially relevant again.
    $this->actingAs($user)->post("/presence-sessions/{$sessionId}/evidence-photos", [
        'photo' => UploadedFile::fake()->image('evidence.jpg'),
    ]);

    $transitions = AuditEvent::query()->where('event_name', 'queue_presence.presence_confidence_computed')->orderBy('created_at')->get();
    expect($transitions)->toHaveCount(2)
        ->and($transitions[0]->payload['new_tier'])->toBe('location_verified')
        ->and($transitions[1]->payload['new_tier'])->toBe('evidence_verified');
});

it('also audits an existing Queues module event, proving the sink is shared, not module-specific', function () {
    JurisdictionRuleModel::query()->create([
        'id' => (string) Str::uuid(),
        'jurisdiction_country' => 'US',
        'category' => null,
        'permitted' => true,
        'effective_from' => now()->subYear(),
        'effective_to' => null,
    ]);
    $admin = User::factory()->create(['is_admin' => true]);
    $seller = User::factory()->create();

    $this->actingAs($seller)->post('/queues', [
        'category' => 'concert',
        'jurisdiction_country' => 'US',
        'latitude' => 32.7157,
        'longitude' => -117.1611,
        'radius_meters' => 200,
    ]);
    $queue = QueueModel::query()->first();

    $this->actingAs($admin)->post("/admin/queues/{$queue->id}/approve");

    $audit = AuditEvent::query()->where('event_name', 'queues.queue_approved')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->subject_type)->toBe('queue')
        ->and($audit->subject_id)->toBe($queue->id);
});
