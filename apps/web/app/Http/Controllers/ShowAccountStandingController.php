<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RowBuddy\Administration\Contracts\AccountStandingRepository;

/**
 * Mobile Sprint 6 (ADR-028 §3): "a small, new read endpoint exposing
 * the account owner's own suspended/active status. The underlying
 * AccountStandingLookup port (ADR-026) already exists for enforcement;
 * nothing today lets the account owner see their own standing." The
 * first HTTP surface of any kind — web or mobile — for this read.
 * requestingUserId is derived exclusively from the authenticated
 * user; there is no id-accepting parameter to guard against an IDOR
 * attempt in the first place.
 */
final class ShowAccountStandingController extends Controller
{
    public function __invoke(Request $request, AccountStandingRepository $standings): JsonResponse
    {
        $state = $standings->findStanding((string) $request->user()->id);

        return response()->json(['data' => ['state' => $state->value]]);
    }
}
