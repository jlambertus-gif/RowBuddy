<?php

declare(strict_types=1);

arch('laravel preset')->preset()->laravel();

// RegisterApiUserController and SendApiEmailVerificationNotificationController
// (ADR-028 Decision 3) must compute sha1($user->getEmailForVerification())
// themselves, byte-identical to Illuminate\Auth\Notifications\VerifyEmail's
// own internal hash — required for interoperability with Laravel's
// existing signed-URL verification check (VerifyEmailRequest), not a
// weakened-hash security choice. Every other use of these functions
// anywhere else in App\* remains forbidden.
arch('security preset')->preset()->security()->ignoring([
    'App\Http\Controllers\RegisterApiUserController',
    'App\Http\Controllers\SendApiEmailVerificationNotificationController',
]);
