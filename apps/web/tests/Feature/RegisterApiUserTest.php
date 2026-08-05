<?php

declare(strict_types=1);

use App\Mail\VerifyEmailMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('registers a new user and issues a Sanctum token (feature)', function () {
    Mail::fake();

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Mobile Registrant',
        'email' => 'mobile-registrant@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ]);

    $response->assertCreated();

    $user = User::query()->where('email', 'mobile-registrant@example.com')->firstOrFail();
    expect($user->hasVerifiedEmail())->toBeFalse();

    $token = $response->json('data.token');
    expect($token)->toBeString()->not->toBeEmpty();

    $me = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/me');
    $me->assertOk()->assertJsonPath('data.id', $user->id);

    // VerifyEmailMail implements ShouldQueue, so a real send here is
    // dispatched to the queue, not sent inline — MailFake tracks that
    // under assertQueued(), not assertSent().
    Mail::assertQueued(VerifyEmailMail::class);
});

it('rejects registration with a duplicate email (feature, validation)', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Someone Else',
        'email' => 'existing@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('email');
});

it('rejects registration when the password confirmation does not match (feature, validation)', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Mismatched Password',
        'email' => 'mismatched@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'a-totally-different-password',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('password');
});

it('returns exactly the documented response shape and never a password hash (contract)', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Contract Check',
        'email' => 'contract-check@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ]);

    $response->assertCreated()->assertJsonStructure([
        'data' => ['user' => ['id', 'name', 'email', 'email_verified_at'], 'token'],
    ]);

    expect(array_keys($response->json('data.user')))->toBe(['id', 'name', 'email', 'email_verified_at'])
        ->and($response->json('data.user.email_verified_at'))->toBeNull()
        ->and($response->getContent())->not->toContain('$2y$');
});
