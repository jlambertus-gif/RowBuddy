<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;

/**
 * Shared response-shaping for the buyer-payment-method controllers.
 * Deliberately never renders `stripeCustomerId`/`stripePaymentMethodId`
 * to the frontend (ADR-027 Architecture Refinements §4) — only the
 * SetupIntent client secret Stripe.js strictly requires, and afterwards
 * only a plain confirmation flag.
 */
trait RendersBuyerPaymentMethodResponses
{
    private function setupIntentNotConfirmed(): JsonResponse
    {
        return response()->json(['message' => __('payments.errors.setup_intent_not_confirmed')], 422);
    }

    private function setupIntentMismatch(): JsonResponse
    {
        return response()->json(['message' => __('payments.errors.setup_intent_mismatch')], 403);
    }
}
