<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

/**
 * Mobile Sprint 4 (ADR-028 §3/§4). Mobile is the first-ever writer of
 * language/country_code/currency/timezone (Decision 4) — these columns
 * have existed since Phase 7 but were read-only until this endpoint.
 */
it('rejects an unauthenticated profile read (authorization)', function () {
    $this->getJson('/api/v1/profile')->assertUnauthorized();
});

it('rejects an unauthenticated profile update (authorization)', function () {
    $this->putJson('/api/v1/profile', ['name' => 'Someone'])->assertUnauthorized();
});

it('shows the authenticated user their own profile, including locale preferences (feature, contract)', function () {
    $user = User::factory()->create(['name' => 'Ada Lovelace', 'language' => 'es']);
    $token = $user->createToken('device')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/profile');

    $response->assertOk();
    $response->assertJsonStructure(['data' => ['name', 'email', 'language', 'country_code', 'currency', 'timezone']]);
    expect($response->json('data.name'))->toBe('Ada Lovelace')
        ->and($response->json('data.language'))->toBe('es');
});

it('writes language/country_code/currency/timezone for the first time via a Sanctum token (feature, contract)', function () {
    $user = User::factory()->create();
    $token = $user->createToken('device')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")->putJson('/api/v1/profile', [
        'name' => $user->name,
        'email' => $user->email,
        'language' => 'es',
        'country_code' => 'MX',
        'currency' => 'MXN',
        'timezone' => 'America/Mexico_City',
    ]);

    $response->assertOk();
    expect($response->json('data'))->toMatchArray([
        'language' => 'es',
        'country_code' => 'MX',
        'currency' => 'MXN',
        'timezone' => 'America/Mexico_City',
    ]);

    $user->refresh();
    expect($user->language)->toBe('es')
        ->and($user->country_code)->toBe('MX')
        ->and($user->currency)->toBe('MXN')
        ->and($user->timezone)->toBe('America/Mexico_City');
});

it('leaves previously-set locale preferences untouched when a later update omits them', function () {
    $user = User::factory()->create();
    $token = $user->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")->putJson('/api/v1/profile', [
        'name' => $user->name,
        'email' => $user->email,
        'language' => 'es',
    ])->assertOk();

    $this->withHeader('Authorization', "Bearer {$token}")->putJson('/api/v1/profile', [
        'name' => 'A New Name',
        'email' => $user->email,
    ])->assertOk();

    $user->refresh();
    expect($user->name)->toBe('A New Name')
        ->and($user->language)->toBe('es');
});

it('never accepts a client-supplied user identity, always updating only the authenticated token owner (IDOR)', function () {
    $user = User::factory()->create();
    $impersonatedTarget = User::factory()->create(['name' => 'Untouched']);
    $token = $user->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")->putJson('/api/v1/profile', [
        'name' => 'Updated Name',
        'email' => $user->email,
        'id' => $impersonatedTarget->id,
    ])->assertOk();

    $impersonatedTarget->refresh();
    expect($impersonatedTarget->name)->toBe('Untouched');
});

it('rejects an unsupported language code', function () {
    $user = User::factory()->create();
    $token = $user->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")->putJson('/api/v1/profile', [
        'name' => $user->name,
        'email' => $user->email,
        'language' => 'fr',
    ])->assertStatus(422);
});

it('rejects a malformed currency code', function () {
    $user = User::factory()->create();
    $token = $user->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")->putJson('/api/v1/profile', [
        'name' => $user->name,
        'email' => $user->email,
        'currency' => 'US',
    ])->assertStatus(422);
});

it('rejects an invalid timezone identifier', function () {
    $user = User::factory()->create();
    $token = $user->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")->putJson('/api/v1/profile', [
        'name' => $user->name,
        'email' => $user->email,
        'timezone' => 'Not/AZone',
    ])->assertStatus(422);
});

it('resets email verification and re-sends the notification when the email address changes', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'old@example.test']);
    $token = $user->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")->putJson('/api/v1/profile', [
        'name' => $user->name,
        'email' => 'new@example.test',
    ])->assertOk();

    $user->refresh();
    expect($user->email)->toBe('new@example.test')
        ->and($user->email_verified_at)->toBeNull();
    Notification::assertSentTo($user, VerifyEmail::class);
});
