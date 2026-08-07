<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RowBuddy\Administration\Infrastructure\Eloquent\AccountStandingModel;

uses(RefreshDatabase::class);

/**
 * Mobile Sprint 6 (ADR-028 §3). The first HTTP surface — web or
 * mobile — for a user's own account standing. There is no id-accepting
 * parameter at all (the requester's own token is the only input), so
 * the IDOR case here is "does a second user's token see a second,
 * independent standing" rather than a classic cross-resource-id probe.
 */
it('rejects an unauthenticated request (authorization)', function () {
    $this->getJson('/api/v1/account-standing')->assertUnauthorized();
});

it('reports active for a user with no persisted standing row (feature, contract)', function () {
    $user = User::factory()->create();
    $token = $user->createToken('device')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/account-standing');

    $response->assertOk();
    $response->assertJsonStructure(['data' => ['state']]);
    expect($response->json('data.state'))->toBe('active');
});

it('reports suspended for a suspended user', function () {
    $user = User::factory()->create();
    AccountStandingModel::query()->create(['user_id' => $user->id, 'state' => 'suspended']);
    $token = $user->createToken('device')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/account-standing');

    $response->assertOk();
    expect($response->json('data.state'))->toBe('suspended');
});

it("never reports another user's standing, even when one user is suspended and another is not (IDOR)", function () {
    $suspendedUser = User::factory()->create();
    AccountStandingModel::query()->create(['user_id' => $suspendedUser->id, 'state' => 'suspended']);
    $activeUser = User::factory()->create();
    $suspendedToken = $suspendedUser->createToken('device')->plainTextToken;
    $activeToken = $activeUser->createToken('device')->plainTextToken;

    $suspendedResponse = $this->withHeader('Authorization', "Bearer {$suspendedToken}")
        ->getJson('/api/v1/account-standing');

    // The AuthManager/RequestGuard caches the resolved user across
    // simulated requests within one test method — required whenever a
    // test authenticates as a second, different user.
    $this->app['auth']->forgetGuards();

    $activeResponse = $this->withHeader('Authorization', "Bearer {$activeToken}")
        ->getJson('/api/v1/account-standing');

    expect($suspendedResponse->json('data.state'))->toBe('suspended')
        ->and($activeResponse->json('data.state'))->toBe('active');
});
