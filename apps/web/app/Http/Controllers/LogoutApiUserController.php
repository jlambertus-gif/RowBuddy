<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;
use RowBuddy\Notifications\Contracts\DeviceTokenRepository;

/**
 * POST /api/v1/auth/logout (ADR-028 Decision 3). Revokes only the
 * token that authenticated this exact request — never any other token
 * belonging to the same user, and never reachable for any other user's
 * token (the token to delete is derived exclusively from the
 * authenticated request itself, never a client-supplied token id).
 *
 * Additively extended for Decision 6: "a token is removed on logout...
 * never pushed to once its owning session's token has been revoked."
 * The client passes its own already-known expo_push_token back
 * (optional, backward compatible with any caller that omits it) —
 * best-effort, silently a no-op if absent or already removed.
 */
final class LogoutApiUserController extends Controller
{
    public function __invoke(Request $request, DeviceTokenRepository $deviceTokens): Response
    {
        /** @var PersonalAccessToken $token */
        $token = $request->user()->currentAccessToken();
        $token->delete();

        $expoPushToken = $request->string('expo_push_token')->toString();

        if ($expoPushToken !== '') {
            $deviceTokens->deleteByToken((string) $request->user()->id, $expoPushToken);
        }

        return response()->noContent();
    }
}
