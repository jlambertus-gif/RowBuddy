<?php

declare(strict_types=1);

use App\Mail\VerifyEmailMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('resends the verification email for an unverified user (feature)', function () {
    Mail::fake();
    $user = User::factory()->unverified()->create();
    $token = $user->createToken('device')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/email/verification-notification');

    $response->assertStatus(202)->assertExactJson(['data' => ['sent' => true]]);
    // VerifyEmailMail implements ShouldQueue, so a real send here is
    // dispatched to the queue, not sent inline.
    Mail::assertQueued(VerifyEmailMail::class);
});

it('is a no-op for an already-verified user and sends nothing (feature)', function () {
    Mail::fake();
    $user = User::factory()->create();
    $token = $user->createToken('device')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/email/verification-notification');

    $response->assertOk()->assertExactJson(['data' => ['already_verified' => true]]);
    Mail::assertNothingSent();
    Mail::assertNothingQueued();
});

it('rejects an unauthenticated request (authorization)', function () {
    $this->postJson('/api/v1/auth/email/verification-notification')->assertUnauthorized();
});

it('rate-limits resending, matching web\'s own verification limiter (feature, throttling)', function () {
    $user = User::factory()->unverified()->create();
    $token = $user->createToken('device')->plainTextToken;

    for ($i = 0; $i < 6; $i++) {
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/email/verification-notification')
            ->assertStatus(202);
    }

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/email/verification-notification')
        ->assertStatus(429);
});

it('never sends another user\'s verification email (IDOR)', function () {
    Mail::fake();
    $unverifiedOther = User::factory()->unverified()->create();
    $caller = User::factory()->unverified()->create();
    $callerToken = $caller->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$callerToken}")
        ->postJson('/api/v1/auth/email/verification-notification')
        ->assertStatus(202);

    Mail::assertQueued(VerifyEmailMail::class, fn ($mail) => $mail->hasTo($caller->email));
    Mail::assertNotQueued(VerifyEmailMail::class, fn ($mail) => $mail->hasTo($unverifiedOther->email));
});
