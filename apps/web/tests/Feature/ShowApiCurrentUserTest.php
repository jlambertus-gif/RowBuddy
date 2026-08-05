<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns the authenticated user\'s own identity (feature)', function () {
    $user = User::factory()->create();
    $token = $user->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email);
});

it('rejects an unauthenticated request (authorization)', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});

it('never returns another user\'s identity, regardless of which token authenticates the request (IDOR)', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $tokenA = $userA->createToken('device')->plainTextToken;
    $tokenB = $userB->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$tokenA}")
        ->getJson('/api/v1/me')
        ->assertJsonPath('data.id', $userA->id)
        ->assertJsonMissing(['data' => ['id' => $userB->id]]);

    // Sanctum's request guard memoizes the resolved user on the guard
    // instance, which persists across simulated requests within one
    // test method — harmless in real usage (a real HTTP request is a
    // fresh process), but this forces the next call to actually
    // re-resolve from tokenB rather than reusing userA's cached result.
    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$tokenB}")
        ->getJson('/api/v1/me')
        ->assertJsonPath('data.id', $userB->id)
        ->assertJsonMissing(['data' => ['id' => $userA->id]]);
});

it('returns exactly the documented response shape, no account-standing field (contract)', function () {
    $user = User::factory()->create();
    $token = $user->createToken('device')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/me');

    $response->assertOk()->assertJsonStructure(['data' => ['id', 'name', 'email', 'email_verified_at']]);
    expect(array_keys($response->json('data')))->toBe(['id', 'name', 'email', 'email_verified_at']);
});
