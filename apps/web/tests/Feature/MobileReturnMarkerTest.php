<?php

declare(strict_types=1);

use App\Support\MobileReturnMarker;
use Illuminate\Support\Carbon;

it('round-trips a marker for its own purpose', function () {
    $marker = MobileReturnMarker::make('email_verification');

    expect(MobileReturnMarker::isValid($marker, 'email_verification'))->toBeTrue();
});

it('rejects a marker checked against a different purpose than it was made for', function () {
    $marker = MobileReturnMarker::make('email_verification');

    expect(MobileReturnMarker::isValid($marker, 'password_reset'))->toBeFalse();
});

it('rejects null, empty, and garbage values', function () {
    expect(MobileReturnMarker::isValid(null, 'email_verification'))->toBeFalse()
        ->and(MobileReturnMarker::isValid('', 'email_verification'))->toBeFalse()
        ->and(MobileReturnMarker::isValid('not-encrypted-at-all', 'email_verification'))->toBeFalse()
        ->and(MobileReturnMarker::isValid('https://evil.example.com', 'email_verification'))->toBeFalse();
});

it('rejects a marker after its expiry has passed', function () {
    $marker = MobileReturnMarker::make('email_verification');

    Carbon::setTestNow(now()->addMinutes(61));

    expect(MobileReturnMarker::isValid($marker, 'email_verification'))->toBeFalse();

    Carbon::setTestNow();
});

it('never contains the purpose or expiry in plaintext', function () {
    $marker = MobileReturnMarker::make('email_verification');

    expect($marker)->not->toContain('email_verification')
        ->and($marker)->not->toContain('purpose')
        ->and($marker)->not->toContain('expires_at');
});
