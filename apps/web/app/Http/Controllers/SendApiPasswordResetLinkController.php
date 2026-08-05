<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mail\ResetPasswordMail;
use App\Support\MobileReturnMarker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

/**
 * POST /api/v1/auth/forgot-password (ADR-028 Decision 3). Reuses the
 * same PasswordBroker::sendResetLink() web uses, via its own supported
 * callback parameter — the token itself is created by the identical
 * underlying mechanism; only the mail-sending step differs, so the
 * mobile-return marker (ADR-028 Decision 7) can be embedded in the
 * link. Web's own PasswordResetLinkController is untouched.
 *
 * Always responds identically regardless of whether the account
 * exists, was recently reset (throttled), or a link was actually sent —
 * a deliberate hardening choice for this new API surface, preventing
 * account enumeration via this endpoint. This does not change web's own
 * (different) behavior.
 */
final class SendApiPasswordResetLinkController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        Password::sendResetLink($credentials, function ($user, string $token): void {
            $resetUrl = url(route('password.reset', [
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
                'mobile_return' => MobileReturnMarker::make('password_reset'),
            ], false));

            Mail::to($user->getEmailForPasswordReset())->send(
                new ResetPasswordMail($user->getEmailForPasswordReset(), $resetUrl),
            );
        });

        return response()->json(['data' => ['sent' => true]]);
    }
}
