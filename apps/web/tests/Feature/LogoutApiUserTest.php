<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RowBuddy\Notifications\Infrastructure\Eloquent\DeviceTokenModel;

uses(RefreshDatabase::class);

it('revokes the calling token (feature)', function () {
    $user = User::factory()->create();
    $token = $user->createToken('device-a')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/logout')
        ->assertNoContent();

    // Sanctum's request guard memoizes the resolved user on itself once
    // per guard instance — harmless in real usage (a real HTTP request
    // is a fresh PHP process), but the guard instance persists across
    // multiple simulated requests within one test method, so the next
    // call must force a fresh resolution to actually re-check the
    // (now-deleted) token rather than reusing the cached success.
    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/me')
        ->assertUnauthorized();
});

it('rejects an unauthenticated logout request (authorization)', function () {
    $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
});

it('rejects a request bearing an invalid or already-revoked token (authorization)', function () {
    $this->withHeader('Authorization', 'Bearer not-a-real-token')
        ->postJson('/api/v1/auth/logout')
        ->assertUnauthorized();
});

it('revokes only the calling token, never any other token for the same or a different user (IDOR)', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $tokenA = $user->createToken('device-a')->plainTextToken;
    $tokenBSameUser = $user->createToken('device-b')->plainTextToken;
    $otherUsersToken = $otherUser->createToken('other-device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$tokenA}")
        ->postJson('/api/v1/auth/logout')
        ->assertNoContent();

    // See the note in the previous test — required between requests
    // that authenticate as different identities within one test method.
    $this->app['auth']->forgetGuards();
    $this->withHeader('Authorization', "Bearer {$tokenBSameUser}")
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);

    $this->app['auth']->forgetGuards();
    $this->withHeader('Authorization', "Bearer {$otherUsersToken}")
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $otherUser->id);
});

it('removes the device token passed on logout (ADR-028 Decision 6)', function () {
    $user = User::factory()->create();
    $token = $user->createToken('device')->plainTextToken;
    DeviceTokenModel::query()->create([
        'user_id' => $user->id,
        'platform' => 'android',
        'expo_push_token' => 'ExponentPushToken[to-be-removed]',
        'last_seen_at' => now(),
    ]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/logout', ['expo_push_token' => 'ExponentPushToken[to-be-removed]'])
        ->assertNoContent();

    expect(DeviceTokenModel::query()->where('expo_push_token', 'ExponentPushToken[to-be-removed]')->exists())->toBeFalse();
});

it('is a no-op when no expo_push_token is passed, never erroring (backward compatible)', function () {
    $user = User::factory()->create();
    $token = $user->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/logout')
        ->assertNoContent();
});

it('is a no-op when the passed token belongs to another user, never removing it (IDOR)', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $token = $user->createToken('device')->plainTextToken;
    DeviceTokenModel::query()->create([
        'user_id' => $otherUser->id,
        'platform' => 'ios',
        'expo_push_token' => 'ExponentPushToken[other-users-token]',
        'last_seen_at' => now(),
    ]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/logout', ['expo_push_token' => 'ExponentPushToken[other-users-token]'])
        ->assertNoContent();

    // The whole point of this test: a logout request authenticated as
    // $user must never be able to delete a device token registered to
    // $otherUser, even by passing that exact token string.
    expect(DeviceTokenModel::query()->where('expo_push_token', 'ExponentPushToken[other-users-token]')->exists())->toBeTrue();
});
