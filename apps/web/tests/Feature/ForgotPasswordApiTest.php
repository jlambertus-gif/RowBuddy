<?php

declare(strict_types=1);

use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('sends a reset-password email for an existing account (feature)', function () {
    Mail::fake();
    $user = User::factory()->create();

    $response = $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email]);

    $response->assertOk()->assertExactJson(['data' => ['sent' => true]]);
    // ResetPasswordMail implements ShouldQueue, so a real send here is
    // dispatched to the queue, not sent inline.
    Mail::assertQueued(ResetPasswordMail::class);
});

it('responds identically for an unknown email, never revealing whether the account exists (feature, security)', function () {
    Mail::fake();

    $response = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'no-such-account@example.com']);

    $response->assertOk()->assertExactJson(['data' => ['sent' => true]]);
    Mail::assertNothingSent();
    Mail::assertNothingQueued();
});

it('rejects a missing email (feature, validation)', function () {
    $this->postJson('/api/v1/auth/forgot-password', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

it('returns exactly the documented response shape (contract)', function () {
    Mail::fake();
    $user = User::factory()->create();

    $response = $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email]);

    expect($response->json())->toBe(['data' => ['sent' => true]]);
});
