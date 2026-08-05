<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Shared response-shaping for the mobile /api/v1/auth/* and /api/v1/me
 * controllers (ADR-028 Decision 3). Deliberately never renders the
 * password hash, remember_token, or any Sanctum token id beyond the
 * one plaintext token a client is issued at creation time — matching
 * the same "explicit allowlist, never a raw model dump" discipline
 * every other JSON controller in this app already follows (e.g.
 * RendersTransferResponses, RendersBuyerPaymentMethodResponses).
 */
trait RendersApiAuthResponses
{
    /**
     * @return array{id: int, name: string, email: string, email_verified_at: string|null}
     */
    private function apiUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
        ];
    }

    /**
     * A client-supplied label for the issued Sanctum token (ADR-028
     * Decision 2 — "one token per device/session"), never trusted for
     * anything beyond a human-readable label a future device-management
     * screen could display; falls back to a generic name if omitted.
     */
    private function apiDeviceName(Request $request): string
    {
        return (string) $request->string('device_name', 'mobile-device');
    }
}
