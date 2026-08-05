<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Proves a verification/password-reset link was legitimately originated
 * by the mobile API (ADR-028 Decision 7's additive "return to the app"
 * branch), never inferred from User-Agent. The mobile-only controllers
 * (RegisterApiUserController, SendApiPasswordResetLinkController,
 * SendApiEmailVerificationNotificationController) are the only callers
 * of {@see make()} — the marker's mere presence and validity is the
 * explicit signal, never anything read from the request itself.
 *
 * Deliberately carries no reset token, verification hash, email, or any
 * other secret/personal data — only a purpose and an expiry, encrypted
 * (not just signed) so the opaque value reveals nothing even if it ends
 * up in a request log. The eventual redirect target is never derived
 * from this value; it always comes from config('mobile.return_url').
 */
final class MobileReturnMarker
{
    private const TTL_MINUTES = 60;

    public static function make(string $purpose): string
    {
        return Crypt::encryptString(json_encode([
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES)->getTimestamp(),
        ], JSON_THROW_ON_ERROR));
    }

    public static function isValid(?string $marker, string $expectedPurpose): bool
    {
        if ($marker === null || $marker === '') {
            return false;
        }

        try {
            $payload = json_decode(Crypt::decryptString($marker), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return false;
        }

        if (! is_array($payload) || ($payload['purpose'] ?? null) !== $expectedPurpose) {
            return false;
        }

        return is_int($payload['expires_at'] ?? null) && $payload['expires_at'] >= now()->getTimestamp();
    }
}
