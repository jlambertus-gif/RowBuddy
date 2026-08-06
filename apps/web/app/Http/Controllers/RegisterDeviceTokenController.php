<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\RegisterDeviceTokenRequest;
use Illuminate\Http\Response;
use RowBuddy\Notifications\Application\DeviceTokenRegistrationService;

/**
 * Mobile Sprint 4 (ADR-028 Decision 6). requestingUserId is derived
 * exclusively from the authenticated user — never accepted from
 * request input. Registering an already-known expo_push_token again
 * (e.g. the app re-registering on every foreground) is a safe,
 * idempotent refresh, not a duplicate — see
 * EloquentDeviceTokenRepository::registerOrRefresh()'s upsert-by-token
 * behavior.
 */
final class RegisterDeviceTokenController extends Controller
{
    public function __invoke(RegisterDeviceTokenRequest $request, DeviceTokenRegistrationService $service): Response
    {
        $service->register(
            (string) $request->user()->id,
            $request->string('platform')->toString(),
            $request->string('expo_push_token')->toString(),
        );

        return response()->noContent(201);
    }
}
