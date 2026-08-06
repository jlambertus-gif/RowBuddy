<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile Sprint 4 (ADR-028 §3/§4). A dedicated read path, deliberately
 * separate from GET /api/v1/me (Sprint 1, ADR-028 Decision 2) rather than
 * extending that endpoint's response shape — LoginApiUserTest/
 * RegisterApiUserTest already assert /api/v1/me's exact
 * ['id','name','email','email_verified_at'] key set, and this profile
 * view's extra four fields belong to a genuinely different concern
 * (locale preference, ADR-028 Decision 4) than "who is currently
 * authenticated."
 */
final class ShowProfileController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => [
            'name' => $user->name,
            'email' => $user->email,
            'language' => $user->language,
            'country_code' => $user->country_code,
            'currency' => $user->currency,
            'timezone' => $user->timezone,
        ]]);
    }
}
