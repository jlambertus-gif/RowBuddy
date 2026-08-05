<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

it('resets the password with a valid token and lets the user log in with the new password (feature)', function () {
    $user = User::factory()->create(['password' => bcrypt('old-password-value')]);
    $token = Password::broker()->createToken($user);

    $response = $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'brand-new-password-value',
        'password_confirmation' => 'brand-new-password-value',
    ]);

    $response->assertOk()->assertExactJson(['data' => ['reset' => true]]);
    expect(Hash::check('brand-new-password-value', $user->refresh()->password))->toBeTrue();

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'brand-new-password-value',
    ])->assertOk();
});

it('rejects an invalid or expired token without changing the password (feature)', function () {
    $user = User::factory()->create(['password' => bcrypt('old-password-value')]);

    $response = $this->postJson('/api/v1/auth/reset-password', [
        'token' => 'not-a-real-token',
        'email' => $user->email,
        'password' => 'brand-new-password-value',
        'password_confirmation' => 'brand-new-password-value',
    ]);

    $response->assertStatus(422);
    expect(Hash::check('old-password-value', $user->refresh()->password))->toBeTrue();
});

it('rejects a mismatched password confirmation (feature, validation)', function () {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'brand-new-password-value',
        'password_confirmation' => 'a-different-value',
    ])->assertStatus(422)->assertJsonValidationErrors('password');
});

it('returns exactly the documented response shape (contract)', function () {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);

    $response = $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'brand-new-password-value',
        'password_confirmation' => 'brand-new-password-value',
    ]);

    expect($response->json())->toBe(['data' => ['reset' => true]]);
});
