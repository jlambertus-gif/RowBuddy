<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersApiAuthResponses;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * POST /api/v1/auth/login (ADR-028 Decision 3). The route applies the
 * `throttle:login` middleware — the exact same named rate limiter
 * FortifyServiceProvider registers for web login (5/min, keyed by
 * email+IP) — so this reuses web's own active throttle verbatim; no
 * separate limiter is defined here. (Fortify's own login pipeline
 * relies on this same middleware once a named limiter is configured,
 * rather than its in-pipeline EnsureLoginIsNotThrottled action — this
 * app configures one, so that in-pipeline action is never actually
 * exercised by web either.)
 *
 * Uses Auth::guard('web')->validate() — checks credentials against the
 * same guard/provider/hasher web uses, with zero session side effects
 * (unlike guard->attempt(), which logs the user into the session guard
 * state; irrelevant here since routes/api.php runs with no session
 * middleware at all, but validate() is the precise, idiomatic tool for
 * "check credentials only").
 */
final class LoginApiUserController extends Controller
{
    use RendersApiAuthResponses;

    public function __invoke(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->validate($credentials)) {
            throw ValidationException::withMessages([
                'email' => [trans('auth.failed')],
            ]);
        }

        /** @var User $user */
        $user = User::query()->where('email', $credentials['email'])->firstOrFail();
        $token = $user->createToken($this->apiDeviceName($request));

        return response()->json([
            'data' => [
                'user' => $this->apiUser($user),
                'token' => $token->plainTextToken,
            ],
        ]);
    }
}
