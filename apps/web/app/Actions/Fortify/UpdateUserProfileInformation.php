<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use RowBuddy\SharedKernel\Localization\SupportedLocales;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * language/country_code/currency/timezone (Mobile Sprint 4, ADR-028
     * §3/§4) are additive and optional here — the existing web caller
     * (Fortify's own `PUT user/profile-information`) never sends them, so
     * its behavior is completely unchanged; this is their first-ever
     * writer, extending rather than forking "the existing profile-update
     * path" per ADR-028 §3.
     *
     * @param  array<string, string|null>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],

            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],

            'language' => ['sometimes', 'nullable', 'string', Rule::in(SupportedLocales::ALL)],
            'country_code' => ['sometimes', 'nullable', 'string', 'size:2'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'timezone' => ['sometimes', 'nullable', 'string', 'timezone'],
        ])->validateWithBag('updateProfileInformation');

        // Arr::only() keeps only the keys actually present in $input, so
        // an omitted field (the existing web caller never sends these
        // four at all) leaves that column untouched by forceFill — never
        // coerced to null just because the key was absent from the
        // request.
        $localePreferences = Arr::only($input, ['language', 'country_code', 'currency', 'timezone']);

        // User always implements MustVerifyEmail (Phase 9 Sprint 5 security
        // review) — an email change always resets verification status and
        // re-triggers the notification, never just an unconditional save.
        if ($input['email'] !== $user->email) {
            $this->updateVerifiedUser($user, $input, $localePreferences);
        } else {
            $user->forceFill([
                'name' => $input['name'],
                'email' => $input['email'],
                ...$localePreferences,
            ])->save();
        }
    }

    /**
     * Update the given verified user's profile information.
     *
     * @param  array<string, string>  $input
     * @param  array<string, string|null>  $localePreferences
     */
    protected function updateVerifiedUser(User $user, array $input, array $localePreferences): void
    {
        $user->forceFill([
            'name' => $input['name'],
            'email' => $input['email'],
            'email_verified_at' => null,
            ...$localePreferences,
        ])->save();

        $user->sendEmailVerificationNotification();
    }
}
