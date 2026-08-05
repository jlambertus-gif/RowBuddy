<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Actions\CompletePasswordReset;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

/**
 * POST /api/v1/auth/reset-password (ADR-028 Decision 3). Reuses the
 * same PasswordBroker::reset() flow web's NewPasswordController uses,
 * resolving App\Actions\Fortify\ResetUserPassword via its contract
 * exactly as web does, plus the same CompletePasswordReset action
 * (remember-token rotation + the PasswordReset event) — the only
 * difference from web is the response shape (no interstitial/marker
 * logic here; that lives entirely in the mobile-return response
 * classes reached via the *web* reset-password form, see ADR-028
 * Decision 7).
 */
final class ResetApiPasswordController extends Controller
{
    public function __invoke(Request $request, ResetsUserPasswords $resetter, StatefulGuard $guard): JsonResponse
    {
        $credentials = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'password_confirmation' => ['required', 'string'],
        ]);

        $status = Password::reset($credentials, function ($user) use ($resetter, $credentials, $guard): void {
            $resetter->reset($user, $credentials);

            app(CompletePasswordReset::class)($guard, $user);
        });

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['message' => trans($status)], 422);
        }

        return response()->json(['data' => ['reset' => true]]);
    }
}
