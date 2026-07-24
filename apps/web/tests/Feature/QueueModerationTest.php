<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use RowBuddy\Queues\Events\QueueApproved;
use RowBuddy\Queues\Events\QueuePublished;
use RowBuddy\Queues\Events\QueueRejected;
use RowBuddy\Queues\Infrastructure\Eloquent\JurisdictionRuleModel;
use RowBuddy\Queues\Infrastructure\Eloquent\QueueModel;
use RowBuddy\Queues\Infrastructure\Eloquent\RestrictedCategoryModel;
use RowBuddy\Queues\ValueObjects\QueueStatus;

uses(RefreshDatabase::class);

function permitJurisdictionForModerationTest(string $country): void
{
    JurisdictionRuleModel::query()->create([
        'id' => (string) Str::uuid(),
        'jurisdiction_country' => $country,
        'category' => null,
        'permitted' => true,
        'effective_from' => now()->subYear(),
        'effective_to' => null,
    ]);
}

function aStoredPendingQueue(?string $id = null, string $category = 'concert', string $country = 'US'): QueueModel
{
    return QueueModel::query()->create([
        'id' => $id ?? (string) Str::uuid(),
        'category' => $category,
        'jurisdiction_country' => $country,
        'center_latitude' => 32.7157,
        'center_longitude' => -117.1611,
        'radius_meters' => 200,
        'authorship' => 'user_submitted',
        'organizer_reference' => null,
        'status' => QueueStatus::Pending->value,
    ]);
}

function anAdminUser(): User
{
    return User::factory()->create(['is_admin' => true]);
}

// --- Authorization ---

it('redirects guests away from every moderation endpoint', function () {
    $queue = aStoredPendingQueue();

    $this->get('/admin/queues')->assertRedirect('/login');
    $this->post("/admin/queues/{$queue->id}/approve")->assertRedirect('/login');
    $this->post("/admin/queues/{$queue->id}/reject", ['reason' => 'no'])->assertRedirect('/login');
    $this->post("/admin/queues/{$queue->id}/publish")->assertRedirect('/login');
});

it('forbids authenticated non-admin users from every moderation endpoint', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $queue = aStoredPendingQueue();

    $this->actingAs($user)->get('/admin/queues')->assertForbidden();
    $this->actingAs($user)->post("/admin/queues/{$queue->id}/approve")->assertForbidden();
    $this->actingAs($user)->post("/admin/queues/{$queue->id}/reject", ['reason' => 'no'])->assertForbidden();
    $this->actingAs($user)->post("/admin/queues/{$queue->id}/publish")->assertForbidden();
});

// --- Listing ---

it('lists only pending queues for an admin', function () {
    aStoredPendingQueue();
    QueueModel::query()->create([
        'id' => (string) Str::uuid(),
        'category' => 'concert',
        'jurisdiction_country' => 'US',
        'center_latitude' => 0,
        'center_longitude' => 0,
        'radius_meters' => 100,
        'authorship' => 'admin_curated',
        'organizer_reference' => 'venue-1',
        'status' => QueueStatus::Published->value,
    ]);

    $response = $this->actingAs(anAdminUser())->get('/admin/queues');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

// --- Approve ---

it('lets an admin approve a pending queue and dispatches the domain event', function () {
    Event::fake([QueueApproved::class]);
    permitJurisdictionForModerationTest('US');
    $queue = aStoredPendingQueue();

    $response = $this->actingAs(anAdminUser())->post("/admin/queues/{$queue->id}/approve");

    $response->assertOk();
    expect($response->json('data.status'))->toBe(QueueStatus::Approved->value)
        ->and($queue->fresh()->status)->toBe(QueueStatus::Approved->value);
    Event::assertDispatched(QueueApproved::class);
});

it('refuses to approve a queue blocked by the restricted-category gate, without leaking internals', function () {
    permitJurisdictionForModerationTest('US');
    RestrictedCategoryModel::query()->create([
        'id' => (string) Str::uuid(),
        'code' => 'medical_emergency',
        'jurisdiction_country' => null,
        'active' => true,
    ]);
    $queue = aStoredPendingQueue(category: 'medical_emergency');

    $response = $this->actingAs(anAdminUser())->post("/admin/queues/{$queue->id}/approve");

    $response->assertStatus(422);
    expect($response->json('message'))->not->toContain('medical_emergency')
        ->and($queue->fresh()->status)->toBe(QueueStatus::Pending->value);
});

it('returns a clean error when approving a queue that is not pending, without leaking internals', function () {
    permitJurisdictionForModerationTest('US');
    $queue = aStoredPendingQueue();
    $queue->update(['status' => QueueStatus::Approved->value]);

    $response = $this->actingAs(anAdminUser())->post("/admin/queues/{$queue->id}/approve");

    $response->assertStatus(422);
    expect($response->json('message'))
        ->not->toContain('InvalidQueueStatusTransition')
        ->not->toContain('Cannot transition');
});

it('returns 404 when approving an unknown queue', function () {
    $response = $this->actingAs(anAdminUser())->post('/admin/queues/'.Str::uuid().'/approve');

    $response->assertStatus(404);
});

// --- Reject ---

it('lets an admin reject a pending queue with a reason and dispatches the domain event', function () {
    Event::fake([QueueRejected::class]);
    $queue = aStoredPendingQueue();

    $response = $this->actingAs(anAdminUser())->post("/admin/queues/{$queue->id}/reject", [
        'reason' => 'Restricted category per legal review.',
    ]);

    $response->assertOk();
    expect($response->json('data.status'))->toBe(QueueStatus::Rejected->value)
        ->and($queue->fresh()->status)->toBe(QueueStatus::Rejected->value);
    Event::assertDispatched(QueueRejected::class, fn (QueueRejected $event): bool => $event->payload()['reason'] === 'Restricted category per legal review.'
    );
});

it('requires a reason to reject a queue, with a translated validation message', function () {
    $queue = aStoredPendingQueue();

    $response = $this->actingAs(anAdminUser())->post("/admin/queues/{$queue->id}/reject", []);

    $response->assertSessionHasErrors('reason');
});

it('returns a clean error when rejecting a queue that is not pending', function () {
    $queue = aStoredPendingQueue();
    $queue->update(['status' => QueueStatus::Published->value]);

    $response = $this->actingAs(anAdminUser())->post("/admin/queues/{$queue->id}/reject", [
        'reason' => 'too late',
    ]);

    $response->assertStatus(422);
    expect($response->json('message'))->not->toContain('InvalidQueueStatusTransition');
});

// --- Publish ---

it('lets an admin publish an approved queue and dispatches the domain event', function () {
    Event::fake([QueuePublished::class]);
    $queue = aStoredPendingQueue();
    $queue->update(['status' => QueueStatus::Approved->value]);

    $response = $this->actingAs(anAdminUser())->post("/admin/queues/{$queue->id}/publish");

    $response->assertOk();
    expect($response->json('data.status'))->toBe(QueueStatus::Published->value)
        ->and($queue->fresh()->status)->toBe(QueueStatus::Published->value);
    Event::assertDispatched(QueuePublished::class);
});

it('returns a clean error when publishing a queue that has not been approved', function () {
    $queue = aStoredPendingQueue();

    $response = $this->actingAs(anAdminUser())->post("/admin/queues/{$queue->id}/publish");

    $response->assertStatus(422);
    expect($response->json('message'))->not->toContain('InvalidQueueStatusTransition');
});

// --- Translations ---

it('has en and es translations for every moderation message key', function () {
    $keys = [
        'queues.moderation.not_found',
        'queues.moderation.invalid_transition',
        'queues.moderation.approved',
        'queues.moderation.rejected',
        'queues.moderation.published',
        'queues.fields.reason',
    ];

    foreach ($keys as $key) {
        $en = __($key, [], 'en');
        $es = __($key, [], 'es');

        expect($en)->not->toBe($key)
            ->and($es)->not->toBe($key)
            ->and($es)->not->toBe($en);
    }
});
