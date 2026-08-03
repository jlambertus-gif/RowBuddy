<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RowBuddy\Payments\Application\BuyerPaymentMethodSetupService;

/**
 * Begins buyer payment-method setup (Phase 9, ADR-027 Sprint 3, ADR-027
 * Architecture Refinements §4). buyerId is derived exclusively from the
 * authenticated user. The response carries only the SetupIntent client
 * secret and the Stripe publishable key Stripe.js needs client-side —
 * never a Stripe Customer or PaymentMethod reference.
 */
final class BeginBuyerPaymentMethodSetupController extends Controller
{
    public function __invoke(Request $request, BuyerPaymentMethodSetupService $service): JsonResponse
    {
        $draft = $service->beginSetup((string) $request->user()->id);

        return response()->json(['data' => [
            'client_secret' => $draft->clientSecret,
            'publishable_key' => (string) config('services.stripe.key'),
        ]]);
    }
}
