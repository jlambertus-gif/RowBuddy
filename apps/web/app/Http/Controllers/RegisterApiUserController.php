<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersApiAuthResponses;
use App\Mail\VerifyEmailMail;
use App\Models\User;
use App\Support\MobileReturnMarker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * POST /api/v1/auth/register (ADR-028 Decision 3). Reuses the exact
 * same CreatesNewUsers contract/action web registration uses — no new
 * validation or account-creation logic. Issues a Sanctum token
 * immediately, mirroring web's own posture that an unverified user may
 * still sign in (EmailVerificationTest: "keeps password reset and
 * dashboard/account-status access available to an unverified user").
 *
 * Deliberately does not fire the generic Registered event — in this
 * codebase it has exactly one listener (SendEmailVerificationNotification,
 * wired in AppServiceProvider::boot()), which this controller replaces
 * with a marker-aware equivalent so the verification email can carry
 * the mobile-return marker (ADR-028 Decision 7). If a second Registered
 * listener is ever added for some other purpose, it must be added here
 * explicitly too — it will not fire automatically for mobile
 * registration.
 */
final class RegisterApiUserController extends Controller
{
    use RendersApiAuthResponses;

    public function __invoke(Request $request, CreatesNewUsers $creator): JsonResponse
    {
        /**
         * CreatesNewUsers::create() is declared against the generic
         * Illuminate\Foundation\Auth\User; App\Actions\Fortify\CreateNewUser
         * (the bound implementation) always returns App\Models\User.
         *
         * @var User $user
         */
        $user = $creator->create($request->all());

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
                'mobile_return' => MobileReturnMarker::make('email_verification'),
            ],
        );

        Mail::to($user->getEmailForVerification())->send(
            new VerifyEmailMail($user->getEmailForVerification(), $verificationUrl),
        );

        $token = $user->createToken($this->apiDeviceName($request));

        return response()->json([
            'data' => [
                'user' => $this->apiUser($user),
                'token' => $token->plainTextToken,
            ],
        ], 201);
    }
}
