<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersBuyerPaymentMethodResponses;
use App\Http\Requests\CompleteBuyerPaymentMethodSetupRequest;
use Illuminate\Http\JsonResponse;
use RowBuddy\Payments\Application\BuyerPaymentMethodSetupService;
use RowBuddy\Payments\Exceptions\SetupIntentBuyerMismatch;
use RowBuddy\Payments\Exceptions\SetupIntentNotConfirmed;

/**
 * Completes buyer payment-method setup (Phase 9, ADR-027 Sprint 3).
 * buyerId is derived exclusively from the authenticated user; the
 * SetupIntent's own success and ownership are verified server-side
 * against Stripe itself before anything is persisted (never trusting a
 * client-supplied "it succeeded" claim). The response never exposes the
 * Stripe Customer or PaymentMethod reference just saved.
 */
final class CompleteBuyerPaymentMethodSetupController extends Controller
{
    use RendersBuyerPaymentMethodResponses;

    public function __invoke(CompleteBuyerPaymentMethodSetupRequest $request, BuyerPaymentMethodSetupService $service): JsonResponse
    {
        try {
            $method = $service->completeSetup(
                (string) $request->user()->id,
                $request->string('setup_intent_id')->toString(),
            );
        } catch (SetupIntentNotConfirmed) {
            return $this->setupIntentNotConfirmed();
        } catch (SetupIntentBuyerMismatch) {
            return $this->setupIntentMismatch();
        }

        return response()->json(['data' => [
            'saved' => true,
            'saved_at' => $method->savedAt->format(DATE_ATOM),
        ]]);
    }
}
