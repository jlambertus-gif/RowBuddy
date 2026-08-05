<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mail\VerifyEmailMail;
use App\Support\MobileReturnMarker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * POST /api/v1/auth/email/verification-notification (ADR-028
 * Decision 3). Mirrors Fortify's own EmailVerificationNotificationController's
 * already-verified guard, but — like registration — builds and sends
 * the verification email directly (rather than
 * $request->user()->sendEmailVerificationNotification(), which always
 * builds an unmarked link) so it can carry the mobile-return marker
 * (ADR-028 Decision 7). Throttled identically to web's own resend route
 * via the same config('fortify.limiters.verification') value, applied
 * as route middleware, not duplicated here.
 */
final class SendApiEmailVerificationNotificationController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['data' => ['already_verified' => true]]);
        }

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
                'mobile_return' => MobileReturnMarker::make('email_verification'),
            ],
        );

        Mail::to($user->getEmailForVerification())->send(
            new VerifyEmailMail($user->getEmailForVerification(), $verificationUrl),
        );

        return response()->json(['data' => ['sent' => true]], 202);
    }
}
