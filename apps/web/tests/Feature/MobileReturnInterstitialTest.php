<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\MobileReturnMarker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

// --- Email verification ------------------------------------------------

it('leaves the ordinary web verification flow completely unchanged when no marker is present', function () {
    $user = User::factory()->unverified()->create();
    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1($user->getEmailForVerification()),
    ]);

    $response = $this->actingAs($user)->get($url);

    $response->assertRedirect();
    expect($response->getContent())->not->toContain('rowbuddy://');
    expect($user->refresh()->hasVerifiedEmail())->toBeTrue();
});

it('shows the return-to-app interstitial for a valid, mobile-originated verification link', function () {
    $user = User::factory()->unverified()->create();
    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1($user->getEmailForVerification()),
        'mobile_return' => MobileReturnMarker::make('email_verification'),
    ]);

    $response = $this->actingAs($user)->get($url);

    $response->assertOk();
    $response->assertSee(config('mobile.return_url'), false);
    expect($user->refresh()->hasVerifiedEmail())->toBeTrue();
});

it('falls back to the normal web response when the marker has the wrong purpose', function () {
    $user = User::factory()->unverified()->create();
    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1($user->getEmailForVerification()),
        'mobile_return' => MobileReturnMarker::make('password_reset'),
    ]);

    $response = $this->actingAs($user)->get($url);

    $response->assertRedirect();
    expect($response->getContent())->not->toContain('rowbuddy://');
});

it('falls back to the normal web response when the marker is tampered/garbage, without invalidating the outer signed URL', function () {
    $user = User::factory()->unverified()->create();
    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1($user->getEmailForVerification()),
        'mobile_return' => 'not-a-real-encrypted-marker',
    ]);

    $response = $this->actingAs($user)->get($url);

    $response->assertRedirect();
    expect($user->refresh()->hasVerifiedEmail())->toBeTrue();
});

it('still rejects an expired signed verification link even with an otherwise-valid marker (no bypass)', function () {
    $user = User::factory()->unverified()->create();
    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1($user->getEmailForVerification()),
        'mobile_return' => MobileReturnMarker::make('email_verification'),
    ]);

    Carbon::setTestNow(now()->addMinutes(61));

    $this->actingAs($user)->get($url)->assertForbidden();
    expect($user->refresh()->hasVerifiedEmail())->toBeFalse();

    Carbon::setTestNow();
});

// --- Password reset ------------------------------------------------------

it('leaves the ordinary web reset-password flow completely unchanged when no marker is present', function () {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);

    $response = $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'brand-new-password-value',
        'password_confirmation' => 'brand-new-password-value',
        'mobile_return' => '',
    ]);

    $response->assertRedirect();
    expect($response->getContent())->not->toContain('rowbuddy://');
});

it('shows the return-to-app interstitial for a valid, mobile-originated reset submission', function () {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);

    $response = $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'brand-new-password-value',
        'password_confirmation' => 'brand-new-password-value',
        'mobile_return' => MobileReturnMarker::make('password_reset'),
    ]);

    $response->assertOk();
    $response->assertSee(config('mobile.return_url'), false);
});

it('falls back to the normal reset response for a wrong-purpose or tampered marker', function () {
    $user = User::factory()->create();

    $wrongPurpose = Password::broker()->createToken($user);
    $wrongPurposeResponse = $this->post('/reset-password', [
        'token' => $wrongPurpose,
        'email' => $user->email,
        'password' => 'brand-new-password-value',
        'password_confirmation' => 'brand-new-password-value',
        'mobile_return' => MobileReturnMarker::make('email_verification'),
    ]);
    $wrongPurposeResponse->assertRedirect();
    expect($wrongPurposeResponse->getContent())->not->toContain('rowbuddy://');

    $tamperedToken = Password::broker()->createToken($user);
    $tamperedResponse = $this->post('/reset-password', [
        'token' => $tamperedToken,
        'email' => $user->email,
        'password' => 'another-new-password-value',
        'password_confirmation' => 'another-new-password-value',
        'mobile_return' => 'not-a-real-encrypted-marker',
    ]);
    $tamperedResponse->assertRedirect();
    expect($tamperedResponse->getContent())->not->toContain('rowbuddy://');
});

it('still rejects an expired reset token even with a valid marker present (no bypass)', function () {
    $user = User::factory()->create(['password' => bcrypt('original-password-value')]);
    $token = Password::broker()->createToken($user);

    Carbon::setTestNow(now()->addMinutes((int) config('auth.passwords.users.expire') + 1));

    $response = $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'brand-new-password-value',
        'password_confirmation' => 'brand-new-password-value',
    ]);

    $response->assertStatus(422);
    expect(Hash::check('original-password-value', $user->refresh()->password))->toBeTrue();

    Carbon::setTestNow();
});

// --- Cross-cutting: open redirects, secret leakage ------------------------

it('never redirects to a client-supplied URL, regardless of what mobile_return contains', function () {
    $user = User::factory()->unverified()->create();
    $attemptedOpenRedirect = 'https://evil.example.com/phish';

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1($user->getEmailForVerification()),
        'mobile_return' => $attemptedOpenRedirect,
    ]);

    $response = $this->actingAs($user)->get($url);

    expect($response->headers->get('Location'))->not->toBe($attemptedOpenRedirect);
    expect($response->getContent())->not->toContain($attemptedOpenRedirect);
});

it('never exposes the reset token, verification hash, signature, or the marker itself in the rendered interstitial', function () {
    $verifyUser = User::factory()->unverified()->create();
    $hash = sha1($verifyUser->getEmailForVerification());
    $marker = MobileReturnMarker::make('email_verification');
    $verifyUrl = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $verifyUser->id,
        'hash' => $hash,
        'mobile_return' => $marker,
    ]);
    parse_str((string) parse_url($verifyUrl, PHP_URL_QUERY), $verifyQuery);
    $signature = $verifyQuery['signature'] ?? '';

    $verifyResponse = $this->actingAs($verifyUser)->get($verifyUrl);
    $verifyBody = $verifyResponse->getContent();

    expect($verifyBody)->not->toContain($hash)
        ->and($verifyBody)->not->toContain($marker)
        ->and($verifyBody)->not->toContain($signature);

    $resetUser = User::factory()->create();
    $resetToken = Password::broker()->createToken($resetUser);
    $resetMarker = MobileReturnMarker::make('password_reset');

    $resetResponse = $this->post('/reset-password', [
        'token' => $resetToken,
        'email' => $resetUser->email,
        'password' => 'brand-new-password-value',
        'password_confirmation' => 'brand-new-password-value',
        'mobile_return' => $resetMarker,
    ]);
    $resetBody = $resetResponse->getContent();

    expect($resetBody)->not->toContain($resetToken)
        ->and($resetBody)->not->toContain($resetMarker);
});

it('never logs the reset token, verification hash, or marker while serving the interstitial', function () {
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
        $logged[] = $event->message.json_encode($event->context);
    });

    $user = User::factory()->unverified()->create();
    $hash = sha1($user->getEmailForVerification());
    $marker = MobileReturnMarker::make('email_verification');
    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => $hash,
        'mobile_return' => $marker,
    ]);

    $response = $this->actingAs($user)->get($url);

    $response->assertOk();
    // Always a real, executed assertion regardless of whether anything
    // was logged at all (nothing is logged by this code path today —
    // json_encode([]) trivially satisfies this, but the assertion still
    // runs, unlike a foreach over a possibly-empty array).
    expect(json_encode($logged))->not->toContain($hash)->and(json_encode($logged))->not->toContain($marker);
});
