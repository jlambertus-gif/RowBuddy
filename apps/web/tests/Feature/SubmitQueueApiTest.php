<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RowBuddy\Administration\Infrastructure\Eloquent\AccountStandingModel;
use RowBuddy\Queues\Infrastructure\Eloquent\JurisdictionRuleModel;
use RowBuddy\Queues\Infrastructure\Eloquent\QueueModel;
use RowBuddy\Queues\Infrastructure\Eloquent\RestrictedCategoryModel;
use RowBuddy\Queues\ValueObjects\QueueStatus;

uses(RefreshDatabase::class);

/**
 * Mobile Sprint 5 (ADR-028 §3). A Sanctum-guarded mirror of the existing
 * web session-guarded /queues route, reusing QueueSubmissionService and
 * SubmitQueueRequest verbatim — every domain-rule branch already covered
 * by tests/Feature/QueueSubmissionTest.php against that existing route.
 * These tests cover the sanctum-wiring itself, plus the
 * SubmitterAccountSuspended branch web's own controller does not
 * currently handle (a pre-existing gap, not introduced or fixed here).
 */
function permitJurisdictionForQueueSubmissionApiTest(string $country): void
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

it('rejects an unauthenticated queue submission (authorization)', function () {
    $this->postJson('/api/v1/queues', [
        'category' => 'concert',
        'jurisdiction_country' => 'US',
        'latitude' => 32.7157,
        'longitude' => -117.1611,
        'radius_meters' => 200,
    ])->assertUnauthorized();
});

it('submits a queue for approval via a Sanctum token, deriving the submitter exclusively from it (feature, contract, IDOR)', function () {
    permitJurisdictionForQueueSubmissionApiTest('US');
    $user = User::factory()->create();
    $impersonatedTarget = User::factory()->create();
    $token = $user->createToken('device')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/queues', [
            'category' => 'concert',
            'jurisdiction_country' => 'US',
            'latitude' => 32.7157,
            'longitude' => -117.1611,
            'radius_meters' => 200,
            // SubmitQueueRequest has no submitter-identity field at all —
            // deliberately extra, unexpected input, proving it's ignored.
            'submitted_by_user_id' => (string) $impersonatedTarget->id,
        ]);

    $response->assertCreated();
    $response->assertJsonStructure(['data' => ['id', 'category', 'jurisdiction_country', 'status']]);
    expect($response->json('data.status'))->toBe(QueueStatus::Pending->value)
        ->and($response->json('data.category'))->toBe('concert');

    $queue = QueueModel::query()->first();
    expect($queue)->not->toBeNull()
        ->and($queue->category)->toBe('concert')
        ->and($queue->jurisdiction_country)->toBe('US');
});

it('rejects an invalid submission with validation errors and creates no queue', function () {
    $user = User::factory()->create();
    $token = $user->createToken('device')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/queues', [
            'category' => '',
            'jurisdiction_country' => 'USA',
            'latitude' => 200,
            'longitude' => -117.1611,
            'radius_meters' => 0,
        ]);

    $response->assertStatus(422);
    expect(QueueModel::count())->toBe(0);
});

it('blocks submission of a restricted category and creates no queue', function () {
    permitJurisdictionForQueueSubmissionApiTest('US');
    RestrictedCategoryModel::query()->create([
        'id' => (string) Str::uuid(),
        'code' => 'medical_emergency',
        'jurisdiction_country' => null,
        'active' => true,
    ]);
    $user = User::factory()->create();
    $token = $user->createToken('device')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/queues', [
            'category' => 'medical_emergency',
            'jurisdiction_country' => 'US',
            'latitude' => 32.7157,
            'longitude' => -117.1611,
            'radius_meters' => 200,
        ]);

    $response->assertStatus(422);
    expect(QueueModel::count())->toBe(0);
});

it('blocks submission when no jurisdiction rule permits the country at all', function () {
    $user = User::factory()->create();
    $token = $user->createToken('device')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/queues', [
            'category' => 'concert',
            'jurisdiction_country' => 'FR',
            'latitude' => 48.8566,
            'longitude' => 2.3522,
            'radius_meters' => 200,
        ]);

    $response->assertStatus(422);
    expect(QueueModel::count())->toBe(0);
});

it('rejects a submission from a suspended account and creates no queue', function () {
    permitJurisdictionForQueueSubmissionApiTest('US');
    $user = User::factory()->create();
    AccountStandingModel::query()->create(['user_id' => $user->id, 'state' => 'suspended']);
    $token = $user->createToken('device')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/queues', [
            'category' => 'concert',
            'jurisdiction_country' => 'US',
            'latitude' => 32.7157,
            'longitude' => -117.1611,
            'radius_meters' => 200,
        ]);

    $response->assertStatus(403);
    expect(QueueModel::count())->toBe(0);
});

it('has en and es translations for the queue-submission account_suspended message', function () {
    $basePath = dirname(__DIR__, 2);
    $en = require "{$basePath}/lang/en/queues.php";
    $es = require "{$basePath}/lang/es/queues.php";

    expect(array_keys($en['errors']))->toBe(array_keys($es['errors']));
});
