<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersApiAuthResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/me (ADR-028 Decision 3). Deliberately minimal — identity
 * and verification status only, never account-standing (that self-
 * visibility endpoint is Sprint 5's own, separate scope). The user is
 * always the one the Sanctum guard resolved from the request's bearer
 * token — never a client-supplied identifier.
 */
final class ShowApiCurrentUserController extends Controller
{
    use RendersApiAuthResponses;

    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->apiUser($request->user())]);
    }
}
