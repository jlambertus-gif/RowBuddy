<?php

declare(strict_types=1);

namespace App\Support\Fortify;

use App\Support\MobileReturnMarker;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use Laravel\Fortify\Http\Responses\VerifyEmailResponse as DefaultVerifyEmailResponse;

/**
 * Additive branch only (ADR-028 Decision 7): when the verification link
 * carries a valid, mobile-originated marker, render the "return to the
 * app" interstitial instead of Fortify's own default redirect. Every
 * other case — no marker, invalid, tampered, expired, or wrong purpose —
 * falls through to the exact, unmodified default response, so ordinary
 * web verification is completely unaffected. The marker is validated
 * independently of (and in addition to) the surrounding signed-URL
 * check the `signed` route middleware already performs.
 *
 * Deliberately lives under App\Support, not App\Http\Responses — this
 * app's own architecture preset (tests/Architecture/PresetTest.php)
 * restricts anything under App\Http to being used only from within
 * App\Http, and this class is bound directly from AppServiceProvider.
 */
final class VerifyEmailResponse implements VerifyEmailResponseContract
{
    public function toResponse($request)
    {
        if (MobileReturnMarker::isValid($request->input('mobile_return'), 'email_verification')) {
            return response()->view('mobile.return-to-app', [
                'title' => __('mobile.return_to_app.verified_title'),
                'returnUrl' => config('mobile.return_url'),
            ]);
        }

        return (new DefaultVerifyEmailResponse)->toResponse($request);
    }
}
