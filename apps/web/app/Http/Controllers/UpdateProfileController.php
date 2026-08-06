<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

/**
 * Mobile Sprint 4 (ADR-028 §3/§4). Extends "the existing profile-update
 * path" rather than forking it — reuses Fortify's own
 * UpdatesUserProfileInformation contract verbatim (bound to
 * App\Actions\Fortify\UpdateUserProfileInformation, the same
 * implementation the web app's `PUT user/profile-information` route
 * already uses), mirroring Laravel\Fortify\Http\Controllers\
 * ProfileInformationController's own shape exactly: no FormRequest here
 * either, since the action's own internal Validator already performs
 * every validation this input needs and throws ValidationException,
 * which Laravel's exception handler already converts to a 422 JSON
 * response for this JSON-only API route.
 *
 * Mobile is this column set's first-ever writer (ADR-028 Decision 4):
 * `language`/`country_code`/`currency`/`timezone` have existed on
 * `users` since Phase 7 (read-only, via
 * EloquentRecipientLocalePreferenceLookup) but nothing before this has
 * ever written them.
 */
final class UpdateProfileController extends Controller
{
    public function __invoke(Request $request, UpdatesUserProfileInformation $updater): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $updater->update($user, $request->all());

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
