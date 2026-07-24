<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RowBuddy\Queues\Infrastructure\Eloquent\JurisdictionRuleModel;
use RowBuddy\Queues\Infrastructure\Eloquent\QueueModel;
use RowBuddy\Queues\Infrastructure\Eloquent\RestrictedCategoryModel;
use RowBuddy\Queues\ValueObjects\QueueStatus;

uses(RefreshDatabase::class);

function permitJurisdictionForQueueSubmissionTest(string $country): void
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

it('redirects unauthenticated users to login and creates no queue', function () {
    $response = $this->post('/queues', [
        'category' => 'concert',
        'jurisdiction_country' => 'US',
        'latitude' => 32.7157,
        'longitude' => -117.1611,
        'radius_meters' => 200,
    ]);

    $response->assertRedirect('/login');
    expect(QueueModel::count())->toBe(0);
});

it('submits a queue for approval when the jurisdiction permits it', function () {
    permitJurisdictionForQueueSubmissionTest('US');
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/queues', [
        'category' => 'concert',
        'jurisdiction_country' => 'US',
        'latitude' => 32.7157,
        'longitude' => -117.1611,
        'radius_meters' => 200,
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect();

    $queue = QueueModel::query()->first();
    expect($queue)->not->toBeNull()
        ->and($queue->status)->toBe(QueueStatus::Pending->value)
        ->and($queue->category)->toBe('concert')
        ->and($queue->jurisdiction_country)->toBe('US');
});

it('rejects an invalid submission with validation errors and creates no queue', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/queues', [
        'category' => '',
        'jurisdiction_country' => 'USA',
        'latitude' => 200,
        'longitude' => -117.1611,
        'radius_meters' => 0,
    ]);

    $response->assertSessionHasErrors(['category', 'jurisdiction_country', 'latitude', 'radius_meters']);
    expect(QueueModel::count())->toBe(0);
});

it('blocks submission of a restricted category and creates no queue', function () {
    permitJurisdictionForQueueSubmissionTest('US');
    RestrictedCategoryModel::query()->create([
        'id' => (string) Str::uuid(),
        'code' => 'medical_emergency',
        'jurisdiction_country' => null,
        'active' => true,
    ]);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/queues', [
        'category' => 'medical_emergency',
        'jurisdiction_country' => 'US',
        'latitude' => 32.7157,
        'longitude' => -117.1611,
        'radius_meters' => 200,
    ]);

    $response->assertSessionHasErrors('category');
    expect(QueueModel::count())->toBe(0);
});

it('blocks submission when no jurisdiction rule permits the country at all', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/queues', [
        'category' => 'concert',
        'jurisdiction_country' => 'FR',
        'latitude' => 48.8566,
        'longitude' => 2.3522,
        'radius_meters' => 200,
    ]);

    $response->assertSessionHasErrors('category');
    expect(QueueModel::count())->toBe(0);
});
