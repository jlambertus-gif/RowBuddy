<?php

declare(strict_types=1);

namespace App\Support\Fortify;

use App\Support\MobileReturnMarker;
use Laravel\Fortify\Contracts\PasswordResetResponse as PasswordResetResponseContract;
use Laravel\Fortify\Http\Responses\PasswordResetResponse as DefaultPasswordResetResponse;

/**
 * Additive branch only (ADR-028 Decision 7): when the reset form was
 * submitted with a valid, mobile-originated marker (threaded through as
 * a hidden field by the web ResetPassword page — see that component),
 * render the "return to the app" interstitial instead of Fortify's own
 * default redirect. Every other case falls through to the exact,
 * unmodified default response, so ordinary web password reset is
 * completely unaffected. The reset itself has already completed
 * successfully by the time this response is built — this class only
 * ever changes what happens *after* a successful reset, never the reset
 * flow itself.
 *
 * Deliberately lives under App\Support, not App\Http\Responses — see
 * the identical note on VerifyEmailResponse in this same directory.
 */
final class PasswordResetResponse implements PasswordResetResponseContract
{
    public function __construct(private readonly string $status) {}

    public function toResponse($request)
    {
        if (MobileReturnMarker::isValid($request->input('mobile_return'), 'password_reset')) {
            return response()->view('mobile.return-to-app', [
                'title' => __('mobile.return_to_app.reset_title'),
                'returnUrl' => config('mobile.return_url'),
            ]);
        }

        return (new DefaultPasswordResetResponse($this->status))->toResponse($request);
    }
}
