<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('logs in with correct credentials and issues a Sanctum token (feature)', function () {
    $user = User::factory()->create(['password' => bcrypt('correct-horse-battery-staple')]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'correct-horse-battery-staple',
    ]);

    $response->assertOk()->assertJsonPath('data.user.id', $user->id);

    $token = $response->json('data.token');
    expect($token)->toBeString()->not->toBeEmpty();

    $this->assertDatabaseHas('personal_access_tokens', [
        'tokenable_id' => $user->id,
        'tokenable_type' => User::class,
    ]);
});

it('rejects an incorrect password without revealing whether the account exists (feature)', function () {
    $user = User::factory()->create(['password' => bcrypt('correct-horse-battery-staple')]);

    $wrongPassword = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'a-completely-wrong-password',
    ]);
    $unknownEmail = $this->postJson('/api/v1/auth/login', [
        'email' => 'no-such-account@example.com',
        'password' => 'a-completely-wrong-password',
    ]);

    $wrongPassword->assertStatus(422)->assertJsonValidationErrors('email');
    $unknownEmail->assertStatus(422)->assertJsonValidationErrors('email');
    expect($wrongPassword->json('errors.email.0'))->toBe($unknownEmail->json('errors.email.0'));
});

it('reuses the same named login rate limiter web uses (feature, throttling)', function () {
    $user = User::factory()->create(['password' => bcrypt('correct-horse-battery-staple')]);

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertStatus(429);
});

it('returns exactly the documented response shape and never a password hash (contract)', function () {
    $user = User::factory()->create(['password' => bcrypt('correct-horse-battery-staple')]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'correct-horse-battery-staple',
    ]);

    $response->assertOk()->assertJsonStructure([
        'data' => ['user' => ['id', 'name', 'email', 'email_verified_at'], 'token'],
    ]);

    expect(array_keys($response->json('data.user')))->toBe(['id', 'name', 'email', 'email_verified_at'])
        ->and($response->getContent())->not->toContain('$2y$');
});
