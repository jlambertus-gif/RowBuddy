<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * POST /api/v1/auth/logout (ADR-028 Decision 3). Revokes only the
 * token that authenticated this exact request — never any other token
 * belonging to the same user, and never reachable for any other user's
 * token (the token to delete is derived exclusively from the
 * authenticated request itself, never a client-supplied token id).
 */
final class LogoutApiUserController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var PersonalAccessToken $token */
        $token = $request->user()->currentAccessToken();
        $token->delete();

        return response()->noContent();
    }
}
