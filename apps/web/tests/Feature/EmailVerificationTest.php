<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RowBuddy\Payments\Contracts\BuyerPaymentMethodGateway;
use RowBuddy\Payments\ValueObjects\ConfirmedPaymentMethod;
use RowBuddy\Payments\ValueObjects\SetupIntentDraft;

uses(RefreshDatabase::class);

it('sends a verification notification when a new user registers', function () {
    Notification::fake();

    $response = $this->post('/register', [
        'name' => 'New Seller',
        'email' => 'new-seller@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ]);

    $response->assertRedirect('/dashboard');

    $user = User::query()->where('email', 'new-seller@example.com')->firstOrFail();
    expect($user->hasVerifiedEmail())->toBeFalse();

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('lets an unverified user sign in', function () {
    $user = User::factory()->unverified()->create(['password' => bcrypt('correct-horse-battery-staple')]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'correct-horse-battery-staple',
    ]);

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user);
});

it('redirects an unverified user to the verification screen from every protected transactional route', function () {
    // This is what the real Inertia-driven frontend experiences: Inertia
    // does not send an Accept: application/json header, so Laravel's
    // built-in 'verified' middleware takes its non-JSON branch and
    // redirects to the verification-notice screen rather than aborting —
    // a materially better UX than a raw error for a real form submission.
    $unverified = User::factory()->unverified()->create();

    $this->actingAs($unverified)->post('/queues', [])
        ->assertRedirect('/email/verify');

    $this->actingAs($unverified)->post('/auctions/'.(string) Str::uuid().'/bids', [])
        ->assertRedirect('/email/verify');

    $this->actingAs($unverified)->post('/presence-sessions', [])
        ->assertRedirect('/email/verify');

    $this->actingAs($unverified)->post('/buyer-payment-methods/setup-intent')
        ->assertRedirect('/email/verify');

    $this->actingAs($unverified)->post('/buyer-payment-methods', ['setup_intent_id' => 'seti_fake_123'])
        ->assertRedirect('/email/verify');
});

it('rejects an unverified JSON API client from every protected transactional route with 403', function () {
    $unverified = User::factory()->unverified()->create();
    $json = ['Accept' => 'application/json'];

    $this->actingAs($unverified)->post('/queues', [], $json)
        ->assertForbidden();

    $this->actingAs($unverified)->post('/auctions/'.(string) Str::uuid().'/bids', [], $json)
        ->assertForbidden();

    $this->actingAs($unverified)->post('/presence-sessions', [], $json)
        ->assertForbidden();

    $this->actingAs($unverified)->post('/buyer-payment-methods/setup-intent', [], $json)
        ->assertForbidden();

    $this->actingAs($unverified)->post('/buyer-payment-methods', ['setup_intent_id' => 'seti_fake_123'], $json)
        ->assertForbidden();
});

it('lets a verified user reach every protected transactional route past the verification gate', function () {
    $verified = User::factory()->create();
    $json = ['Accept' => 'application/json'];

    // Each assertion below proves the request passed the 'verified' gate
    // and reached real controller/validation logic — none of them is a
    // 403, which is the only status the gate itself can produce.
    $this->actingAs($verified)->post('/queues', [], $json)
        ->assertStatus(422);

    $this->actingAs($verified)->post('/auctions/'.(string) Str::uuid().'/bids', [
        'amount_minor_units' => 1500,
        'currency' => 'USD',
    ], $json)->assertStatus(400); // missing Idempotency-Key header, not a 403

    $this->actingAs($verified)->post('/presence-sessions', [], $json)
        ->assertStatus(422);

    $this->app->bind(BuyerPaymentMethodGateway::class, fn () => new class implements BuyerPaymentMethodGateway
    {
        public function createCustomer(string $buyerId): string
        {
            return 'cus_fake_'.$buyerId;
        }

        public function createSetupIntent(string $stripeCustomerId): SetupIntentDraft
        {
            return new SetupIntentDraft($stripeCustomerId, 'seti_fake_123', 'seti_fake_123_secret_abc');
        }

        public function retrieveConfirmedPaymentMethod(string $setupIntentId, string $buyerId): ConfirmedPaymentMethod
        {
            return new ConfirmedPaymentMethod('cus_fake_'.$buyerId, 'pm_fake_'.$buyerId);
        }
    });

    $this->actingAs($verified)->post('/buyer-payment-methods/setup-intent')
        ->assertOk();

    $this->actingAs($verified)->post('/buyer-payment-methods', ['setup_intent_id' => 'seti_fake_123'])
        ->assertOk();
});

it('verifies email with a validly signed link and rejects a tampered one', function () {
    $user = User::factory()->unverified()->create();

    $validUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $tamperedUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('someone-else@example.com')],
    );

    $this->actingAs($user)->get($tamperedUrl)->assertForbidden();
    expect($user->refresh()->hasVerifiedEmail())->toBeFalse();

    $this->actingAs($user)->get($validUrl)->assertRedirect();
    expect($user->refresh()->hasVerifiedEmail())->toBeTrue();
});

it('rejects an expired verification link even with a correct hash', function () {
    $user = User::factory()->unverified()->create();

    $expiredUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->subMinute(),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->actingAs($user)->get($expiredUrl)->assertForbidden();
    expect($user->refresh()->hasVerifiedEmail())->toBeFalse();
});

it('rate-limits resending the verification email', function () {
    $user = User::factory()->unverified()->create();

    for ($i = 0; $i < 6; $i++) {
        $this->actingAs($user)->post('/email/verification-notification')
            ->assertStatus(302);
    }

    $this->actingAs($user)->post('/email/verification-notification')
        ->assertStatus(429);
});

it('keeps password reset and dashboard/account-status access available to an unverified user', function () {
    $unverified = User::factory()->unverified()->create();

    // Password recovery (guest flow) is entirely unaffected by
    // verification status.
    $this->get('/forgot-password')->assertOk();

    // An authenticated-but-unverified user may still change their own
    // password (Fortify's own route, 'auth' only, not 'verified').
    $this->actingAs($unverified)->put('/user/password', [
        'current_password' => 'password',
        'password' => 'a-different-correct-horse-battery',
        'password_confirmation' => 'a-different-correct-horse-battery',
    ])->assertSessionHasNoErrors();

    // Dashboard / account-status information remains viewable while
    // unverified.
    $this->actingAs($unverified)->get('/dashboard')->assertOk();

    // The verification-required screen and the resend action are
    // themselves reachable while unverified (Fortify's own routes,
    // 'auth' only, not 'verified' — asserted here as a completeness
    // check for this sprint's own requirement, not new application code).
    $this->actingAs($unverified)->get('/email/verify')->assertOk();
    $this->actingAs($unverified)->post('/email/verification-notification')->assertStatus(302);
});
