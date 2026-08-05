<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
