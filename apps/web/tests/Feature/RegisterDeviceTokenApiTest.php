<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RowBuddy\Notifications\Infrastructure\Eloquent\DeviceTokenModel;

uses(RefreshDatabase::class);

/**
 * Mobile Sprint 4 (ADR-028 Decision 6). The first HTTP surface
 * DeviceTokenRegistrationService has ever had.
 */
it('rejects an unauthenticated device registration attempt (authorization)', function () {
    $this->postJson('/api/v1/devices', [
        'platform' => 'android',
        'expo_push_token' => 'ExponentPushToken[abc123]',
    ])->assertUnauthorized();
});

it('registers a device token for the authenticated user via a Sanctum token (feature, contract)', function () {
    $user = User::factory()->create();
    $token = $user->createToken('device')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/devices', [
            'platform' => 'android',
            'expo_push_token' => 'ExponentPushToken[abc123]',
        ]);

    $response->assertCreated();
    expect($response->getContent())->toBe('');

    $model = DeviceTokenModel::query()->where('expo_push_token', 'ExponentPushToken[abc123]')->first();
    expect($model)->not->toBeNull()
        ->and((string) $model->user_id)->toBe((string) $user->id)
        ->and($model->platform)->toBe('android');
});

it('refreshes the same token idempotently instead of creating a duplicate row', function () {
    $user = User::factory()->create();
    $token = $user->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/devices', ['platform' => 'ios', 'expo_push_token' => 'ExponentPushToken[same]'])
        ->assertCreated();
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/devices', ['platform' => 'ios', 'expo_push_token' => 'ExponentPushToken[same]'])
        ->assertCreated();

    expect(DeviceTokenModel::query()->where('expo_push_token', 'ExponentPushToken[same]')->count())->toBe(1);
});

it('reassigns a token to whichever user is currently authenticated on that device — intentional, not an IDOR', function () {
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();
    $firstToken = $firstUser->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$firstToken}")
        ->postJson('/api/v1/devices', ['platform' => 'android', 'expo_push_token' => 'ExponentPushToken[shared-device]'])
        ->assertCreated();

    // A device's expo_push_token is stable across whoever is logged in on
    // it (Expo issues one token per physical install, not per account) —
    // the same guard-memoization reset PlaceBidApiTest/RatingApiTest use
    // whenever a test method authenticates as a second, different user.
    $this->app['auth']->forgetGuards();

    $secondToken = $secondUser->createToken('device')->plainTextToken;
    $this->withHeader('Authorization', "Bearer {$secondToken}")
        ->postJson('/api/v1/devices', ['platform' => 'android', 'expo_push_token' => 'ExponentPushToken[shared-device]'])
        ->assertCreated();

    $model = DeviceTokenModel::query()->where('expo_push_token', 'ExponentPushToken[shared-device]')->first();
    expect((string) $model->user_id)->toBe((string) $secondUser->id)
        ->and(DeviceTokenModel::query()->where('expo_push_token', 'ExponentPushToken[shared-device]')->count())->toBe(1);
});

it('rejects an unsupported platform value', function () {
    $user = User::factory()->create();
    $token = $user->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/devices', ['platform' => 'windows', 'expo_push_token' => 'ExponentPushToken[abc]'])
        ->assertStatus(422);
});

it('requires an expo_push_token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/devices', ['platform' => 'android'])
        ->assertStatus(422);
});

it('has en and es translations for every devices message key', function () {
    $basePath = dirname(__DIR__, 2);
    $en = require "{$basePath}/lang/en/devices.php";
    $es = require "{$basePath}/lang/es/devices.php";

    expect(array_keys($en['fields']))->toBe(array_keys($es['fields']));
});
